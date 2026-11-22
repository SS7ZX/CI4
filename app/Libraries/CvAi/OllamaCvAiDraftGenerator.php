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
                            'content' => 'Susun draf CV berbahasa Indonesia profesional yang hanya menggunakan fakta tertulis pada cerita. Jangan mengarang, menerjemahkan bebas, membuat tanggal, jabatan, institusi, angka, pencapaian, atau keahlian yang tidak ada. Tolak kata acak, teks lorem ipsum, placeholder, dan duplikat. Untuk pengalaman dan pendidikan gunakan frasa singkat berbasis fakta. Gunakan daftar kosong jika fakta tidak tersedia. Jangan mengulang identitas, email, nomor telepon, atau alamat. Keluarkan hanya JSON sesuai skema.',
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

        $experience = implode("\n", $this->boundedGroundedList(
            $data,
            'experience',
            $sanitizedStory,
            1000,
            3000,
        ));
        $education = implode("\n", $this->boundedGroundedList(
            $data,
            'education',
            $sanitizedStory,
            1000,
            3000,
        ));
        $skills = implode(', ', $this->boundedGroundedList(
            $data,
            'skills',
            $sanitizedStory,
            120,
            3000,
        ));
        if (mb_strlen($experience) > 3000 || mb_strlen($education) > 3000 || mb_strlen($skills) > 3000) {
            throw new CvAiGenerationException('invalid_response');
        }

        $headline = $this->boundedString($data, 'headline', 160);
        if (! $this->looksLikeProfessionalText($headline, true)) {
            throw new CvAiGenerationException('invalid_response');
        }
        $summary = $this->boundedString($data, 'summary', 3000);
        if ($summary !== '' && ! $this->looksLikeProfessionalText($summary, false)) {
            throw new CvAiGenerationException('invalid_response');
        }

        return new CvAiDraft(
            $headline,
            $summary,
            $experience,
            $education,
            $skills,
        );
    }

    /**
     * @return array{available: bool, model_installed: bool, reason: string}
     */
    public function status(): array
    {
        if (! $this->config->enabled) {
            return [
                'available'       => false,
                'model_installed' => false,
                'reason'          => 'disabled',
            ];
        }

        $parts = parse_url($this->config->ollamaBaseUrl);
        if (
            $parts === false
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array($parts['scheme'], ['http', 'https'], true)
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || ! $this->isLocalRuntimeHost((string) $parts['host'])
        ) {
            return [
                'available'       => false,
                'model_installed' => false,
                'reason'          => 'disabled',
            ];
        }

        try {
            $response = $this->http->get(
                rtrim($this->config->ollamaBaseUrl, '/') . '/api/tags',
                $this->config->healthTimeoutSeconds,
            );
        } catch (HTTPException) {
            return [
                'available'       => false,
                'model_installed' => false,
                'reason'          => 'unavailable',
            ];
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return [
                'available'       => false,
                'model_installed' => false,
                'reason'          => 'unavailable',
            ];
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [
                'available'       => false,
                'model_installed' => false,
                'reason'          => 'invalid_response',
            ];
        }

        $models = is_array($data) && is_array($data['models'] ?? null)
            ? $data['models']
            : [];
        $modelInstalled = false;
        foreach ($models as $model) {
            if (! is_array($model) || ! is_string($model['name'] ?? null)) {
                continue;
            }
            if ($model['name'] === $this->config->model
                || str_starts_with($model['name'], $this->config->model . ':')
            ) {
                $modelInstalled = true;
                break;
            }
        }

        return [
            'available'       => true,
            'model_installed' => $modelInstalled,
            'reason'          => $modelInstalled ? 'ready' : 'model_missing',
        ];
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
    private function boundedGroundedList(
        array $data,
        string $key,
        string $story,
        int $maximumItemLength,
        int $maximumTotalLength,
    ): array {
        $items = $this->boundedList($data, $key, $maximumItemLength, $maximumTotalLength);
        $storyTokens = $this->meaningfulTokens($story);
        $grounded = [];

        foreach ($items as $item) {
            $item = trim($item);
            if (! $this->looksLikeProfessionalText($item, $key === 'skills')) {
                continue;
            }

            $itemTokens = $this->meaningfulTokens($item);
            if ($itemTokens === []) {
                continue;
            }

            $overlap = array_intersect($itemTokens, $storyTokens);
            if ($overlap !== []) {
                $grounded[] = $item;
            }
        }

        return $grounded;
    }

    /**
     * @return list<string>
     */
    private function meaningfulTokens(string $text): array
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        if (preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}+.#-]{1,}/u', $normalized, $matches) !== false) {
            $tokens = array_map(
                static fn (string $token): string => trim($token, '.+#-'),
                $matches[0],
            );

            return array_values(array_unique(array_filter(
                $tokens,
                static fn (string $token): bool => mb_strlen($token, 'UTF-8') >= 2,
            )));
        }

        return [];
    }

    private function looksLikeProfessionalText(string $text, bool $allowShortSkill): bool
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text, 'UTF-8') > 1000) {
            return false;
        }

        $minimumLength = $allowShortSkill ? 2 : 4;
        if (mb_strlen($text, 'UTF-8') < $minimumLength) {
            return false;
        }

        $lower = mb_strtolower($text, 'UTF-8');
        foreach ([
            'lorem ipsum',
            'dummy',
            'placeholder',
            'contoh',
            'inacoo',
            'not available',
            'n/a',
        ] as $placeholder) {
            if (str_contains($lower, $placeholder)) {
                return false;
            }
        }

        if (preg_match('/(.)\1{3,}/u', $text) === 1) {
            return false;
        }

        $letterCount = preg_match_all('/\p{L}/u', $text);
        $totalCount = mb_strlen($text, 'UTF-8');
        if ($letterCount === false || $totalCount === 0 || $letterCount / $totalCount < 0.45) {
            return false;
        }

        return true;
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
