<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

use Throwable;

final class CurlCvAiStreamTransport implements CvAiStreamTransportInterface
{
    /**
     * @param array<string, mixed> $json
     * @param callable(string): void $onChunk
     */
    public function post(string $url, array $json, int $timeoutSeconds, callable $onChunk): int
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new CvAiGenerationException('unavailable');
        }

        $statusCode = 0;
        $callbackException = null;
        $payload = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            curl_setopt_array($handle, [
                CURLOPT_POST            => true,
                CURLOPT_HTTPHEADER      => ['Accept: application/x-ndjson', 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS      => $payload,
                CURLOPT_RETURNTRANSFER  => false,
                CURLOPT_FOLLOWLOCATION  => false,
                CURLOPT_CONNECTTIMEOUT  => min($timeoutSeconds, 10),
                CURLOPT_TIMEOUT         => $timeoutSeconds,
                CURLOPT_HEADERFUNCTION  => static function ($curl, string $header) use (&$statusCode): int {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                        $statusCode = (int) $matches[1];
                    }

                    return strlen($header);
                },
                CURLOPT_WRITEFUNCTION   => static function ($curl, string $chunk) use (
                    &$callbackException,
                    &$statusCode,
                    $onChunk,
                ): int {
                    if (connection_aborted()) {
                        return 0;
                    }

                    if ($statusCode >= 200 && $statusCode < 300) {
                        try {
                            $onChunk($chunk);
                        } catch (Throwable $exception) {
                            $callbackException = $exception;

                            return 0;
                        }
                    }

                    return strlen($chunk);
                },
                CURLOPT_NOPROGRESS      => false,
                CURLOPT_XFERINFOFUNCTION => static function (
                    $curl,
                    float $downloadTotal,
                    float $downloaded,
                    float $uploadTotal,
                    float $uploaded,
                ) use (&$callbackException): int {
                    return connection_aborted() || $callbackException !== null ? 1 : 0;
                },
            ]);

            $result = curl_exec($handle);
            if ($callbackException instanceof Throwable) {
                throw $callbackException;
            }
            if ($result === false) {
                $errorCode = curl_errno($handle);
                if ($errorCode === CURLE_OPERATION_TIMEDOUT) {
                    throw new CvAiGenerationException('timeout');
                }
                if (connection_aborted()) {
                    throw new CvAiGenerationException('cancelled');
                }

                throw new CvAiGenerationException('unavailable');
            }

            return (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }
    }
}
