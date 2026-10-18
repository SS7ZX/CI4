<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Providers;

use App\Libraries\Jobs\Dto\JobPostingDto;
use App\Libraries\Jobs\Dto\ProviderResult;
use App\Libraries\Jobs\Dto\SearchQueryDto;
use App\Libraries\Jobs\JobHttpClientInterface;
use App\Libraries\Jobs\JobProviderException;
use App\Libraries\Jobs\JobProviderInterface;
use App\Libraries\Jobs\CurlJobHttpClient;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use Config\JobMatching;
use Throwable;

final class JoobleProvider implements JobProviderInterface
{
    public function __construct(
        private readonly JobMatching $config,
        private readonly JobHttpClientInterface $http = new CurlJobHttpClient(),
    ) {
    }

    public function search(SearchQueryDto $query): ProviderResult
    {
        if (! $this->isAvailable()) {
            throw new JobProviderException('Penyedia lowongan belum dikonfigurasi.');
        }

        $url = 'https://jooble.org/api/' . rawurlencode($this->config->joobleApiKey);
        $attempts = max(0, $this->config->providerMaxRetries) + 1;
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                $response = $this->http->post($url, [
                    'keywords' => $query->keywords,
                    'location' => $query->location,
                    'page' => $query->page,
                ], $this->config->providerTimeoutSeconds);
                $status = $response->getStatusCode();
                if ($status >= 500 && $attempt + 1 < $attempts) {
                    $this->backoff($attempt);
                    continue;
                }
                if ($status < 200 || $status >= 300) {
                    throw new JobProviderException('Penyedia lowongan menolak permintaan.', false, $status);
                }

                return $this->decodeResponse((string) $response->getBody(), $query->page);
            } catch (JobProviderException $exception) {
                if (! $exception->retryable || $attempt + 1 >= $attempts) {
                    throw $exception;
                }
                $this->backoff($attempt);
            } catch (HTTPException) {
                if ($attempt + 1 >= $attempts) {
                    throw new JobProviderException('Penyedia lowongan tidak dapat dihubungi.');
                }
                $this->backoff($attempt);
            } catch (Throwable) {
                throw new JobProviderException('Kesalahan saat membaca respons penyedia lowongan.');
            }
        }

        throw new JobProviderException('Penyedia lowongan tidak dapat dihubungi.');
    }

    public function name(): string
    {
        return 'jooble';
    }

    public function isAvailable(): bool
    {
        return trim($this->config->joobleApiKey) !== '';
    }

    private function backoff(int $attempt): void
    {
        $backoffs = $this->config->retryBackoffMilliseconds;
        $milliseconds = $backoffs[$attempt]
            ?? ($backoffs !== [] ? $backoffs[array_key_last($backoffs)] : 0);
        if ($milliseconds > 0) {
            usleep((int) $milliseconds * 1000);
        }
    }

    private function decodeResponse(string $body, int $page): ProviderResult
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new JobProviderException('Respons penyedia lowongan tidak valid.', false, null, $exception);
        }
        if (! is_array($data)) {
            throw new JobProviderException('Respons penyedia lowongan tidak valid.');
        }

        $warnings = [];
        $postings = [];
        if (! isset($data['jobs']) || ! is_array($data['jobs'])) {
            $warnings[] = 'Daftar lowongan tidak tersedia pada respons penyedia.';
        } else {
            foreach ($data['jobs'] as $row) {
                if (! is_array($row)) {
                    $warnings[] = 'Satu data lowongan dilewati karena formatnya tidak valid.';
                    continue;
                }
                $posting = new JobPostingDto(
                    $this->optionalString($row['id'] ?? null),
                    is_string($row['title'] ?? null) ? trim($row['title']) : '',
                    $this->optionalString($row['company'] ?? null),
                    $this->optionalString($row['location'] ?? null),
                    is_string($row['snippet'] ?? null) ? $row['snippet'] : '',
                    $this->optionalString($row['salary'] ?? null),
                    $this->optionalString($row['source'] ?? null),
                    $this->optionalString($row['type'] ?? null),
                    is_string($row['link'] ?? null) ? trim($row['link']) : '',
                    $this->optionalString($row['updated'] ?? null),
                    array_intersect_key($row, array_flip([
                        'id', 'title', 'company', 'location', 'snippet', 'salary', 'source', 'type', 'link', 'updated',
                    ])),
                );
                if ($posting->title === '' || $posting->link === '') {
                    $warnings[] = 'Satu data lowongan dilewati karena judul atau tautannya kosong.';
                    continue;
                }
                $postings[] = $posting;
            }
        }

        $count = $data['totalCount'] ?? null;

        return new ProviderResult(
            $postings,
            is_numeric($count) ? max(0, (int) $count) : null,
            $page,
            $warnings,
        );
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
