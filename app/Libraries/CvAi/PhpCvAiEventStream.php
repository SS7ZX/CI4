<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

use CodeIgniter\HTTP\ResponseInterface;
use JsonException;

final class PhpCvAiEventStream implements CvAiEventStreamInterface
{
    public function begin(ResponseInterface $response): void
    {
        $response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'text/event-stream; charset=utf-8')
            ->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->setHeader('X-Accel-Buffering', 'no')
            ->setHeader('Connection', 'keep-alive');
        $response->sendHeaders();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $event, array $payload): bool
    {
        if (connection_aborted()) {
            return false;
        }

        try {
            $data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        echo 'event: ' . $event . "\n";
        echo 'data: ' . $data . "\n\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();

        return ! connection_aborted();
    }
}
