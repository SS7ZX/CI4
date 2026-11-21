<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use Config\CvAi;
use JsonException;

final class OllamaCvAiDraftGenerator implements CvAiDraftGeneratorInterface
{
    private const OUTPUT_SCHEMA = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'properties'           => [
            'headline'   => ['type' => 'string'],
            'summary'    => ['type' => 'string'],
            'experience' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
            'education' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
            'skills' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['headline', 'summary', 'experience', 'education', 'skills'],
    ];

    public function __construct(
        private readonly CvAi $config,
        private readonly CvAiHttpClientInterface $http = new CurlCvAiHttpClient(),
    ) {
    }

    public function generate(string $sanitizedStory): CvAiDraft
    {
        if (! $this->config->enabled || trim($this->config->model) === '') {
            throw new CvAiGenerationException('disabled');
        }

        if (mb_strlen($sanitizedStory) > $this->config->maximumInputCharacters) {
            throw new CvAiGenerationException('input_too_long');
        }

        $url = filter_var($this->config->ollamaBaseUrl, FILTER_VALIDATE_URL);
        $parts = is_string($url) ? parse_url($url) : false;
        if (
            $parts === false
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array($parts['scheme'], ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! $this->isLocalRuntimeHost((string) $parts['host'])
        ) {
            throw new CvAiGenerationException('disabled');
        }

        try {
            $response = $this->http->post(
                rtrim($this->config->ollamaBaseUrl, '/') . '/api/chat',
                [
                    'model'    => $this->config->model,
                    'stream'   => false,
                    'format'   => self::OUTPUT_SCHEMA,
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'Susun draf CV berbahasa Indonesia hanya dari fakta yang tertulis pada cerita. Jangan mengarang tanggal, jabatan, institusi, angka, pencapaian, atau keterampilan. Jika informasi tidak tersedia, isi string kosong atau daftar kosong. Jangan mengulang identitas, email, nomor telepon, atau alamat. Keluarkan hanya JSON sesuai skema.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $sanitizedStory,
                        ],
                    ],
                    'options' => ['temperature' => 0],
                ],
                $this->config->timeoutSeconds,
            );
        } catch (HTTPException $exception) {
            throw new CvAiGenerationException('unavailable', $exception);
        }

        $status = $response->getStatusCode();
        if ($status === 404) {
            throw new CvAiGenerationException('model_missing');
        }
        if ($status < 200 || $status >= 300) {
            throw new CvAiGenerationException('service_error');
        }

        $body = (string) $response->getBody();
        if (strlen($body) > $this->config->maximumOutputBytes) {
            throw new CvAiGenerationException('output_too_large');
        }

        try {
            $envelope = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $content = is_array($envelope) && is_array($envelope['message'] ?? null)
                ? ($envelope['message']['content'] ?? null)
                : null;
            if (! is_string($content) || trim($content) === '') {
                throw new CvAiGenerationException('invalid_response');
            }

            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CvAiGenerationException('invalid_response', $exception);
        }

        if (! is_array($data)) {
            throw new CvAiGenerationException('invalid_response');
        }

        $experience = implode("\n", $this->boundedList($data, 'experience', 1000, 3000));
        $education = implode("\n", $this->boundedList($data, 'education', 1000, 3000));
        $skills = implode(', ', $this->boundedList($data, 'skills', 120, 3000));
        if (mb_strlen($experience) > 3000 || mb_strlen($education) > 3000 || mb_strlen($skills) > 3000) {
            throw new CvAiGenerationException('invalid_response');
        }

        return new CvAiDraft(
            $this->boundedString($data, 'headline', 160),
            $this->boundedString($data, 'summary', 3000),
            $experience,
            $education,
            $skills,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function boundedString(array $data, string $key, int $maximum): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || mb_strlen($value) > $maximum) {
            throw new CvAiGenerationException('invalid_response');
        }

        return trim($value);
    }

    private function isLocalRuntimeHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if (in_array($host, ['localhost', 'host.docker.internal', 'ollama'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function boundedList(array $data, string $key, int $maximumItemLength, int $maximumTotalLength): array
    {
        $items = $data[$key] ?? null;
        if (! is_array($items) || count($items) > 50) {
            throw new CvAiGenerationException('invalid_response');
        }

        $result = [];
        $totalLength = 0;
        foreach ($items as $item) {
            if (! is_string($item) || mb_strlen($item) > $maximumItemLength) {
                throw new CvAiGenerationException('invalid_response');
            }

            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $totalLength += mb_strlen($item);
            if ($totalLength > $maximumTotalLength) {
                throw new CvAiGenerationException('invalid_response');
            }
            $result[] = $item;
        }

        return $result;
    }
}
