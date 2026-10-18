<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Libraries\Jobs\Dto\JobPostingDto;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class JobNormalizer
{
    public function __construct(
        private readonly SalaryParser $salaryParser = new SalaryParser(),
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function normalize(JobPostingDto $posting, string $provider, int $retentionSeconds): ?array
    {
        $title = $this->cleanText($posting->title, 240);
        $link = $this->safeUrl($posting->link);
        if ($title === '' || $link === null) {
            return null;
        }
        $company = $this->nullableText($posting->company, 200);
        $location = $this->nullableText($posting->location, 160);
        $snippet = $this->cleanText($posting->snippet, 5000);
        $salaryText = $this->nullableText($posting->salaryText, 200);
        $salary = $this->salaryParser->parse($salaryText);
        $postedAt = $this->normalizeDate($posting->updatedAt);
        $fetchedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $fetchedAt->modify('+' . max(0, $retentionSeconds) . ' seconds');
        $externalKey = $this->nullableText($posting->externalKey, 160);
        $dedupeSource = $externalKey !== null
            ? mb_strtolower($provider . ':' . $externalKey, 'UTF-8')
            : implode('|', [
                mb_strtolower($title, 'UTF-8'),
                mb_strtolower($company ?? '', 'UTF-8'),
                mb_strtolower($location ?? '', 'UTF-8'),
                $postedAt ?? '',
            ]);

        return [
            'provider' => $provider,
            'external_key' => $externalKey,
            'dedupe_hash' => hash('sha256', $dedupeSource),
            'title' => $title,
            'company' => $company,
            'location' => $location,
            'is_remote' => null,
            'job_type' => $this->normalizeJobType($posting->jobType),
            'salary_text' => $salaryText,
            'salary_min_idr' => $salary->comparable ? $salary->minimumIdr : null,
            'salary_max_idr' => $salary->comparable ? $salary->maximumIdr : null,
            'snippet' => $snippet,
            'source_name' => $this->nullableText($posting->sourceName, 160) ?? $provider,
            'link' => $link,
            'posted_at' => $postedAt,
            'fetched_at' => $fetchedAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'raw' => json_encode([
                'external_key' => $externalKey,
                'title' => $title,
                'company' => $company,
                'location' => $location,
                'job_type' => $posting->jobType,
                'salary' => $salaryText,
                'source' => $posting->sourceName,
                'link' => $link,
                'updated' => $posting->updatedAt,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function safeUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    private function cleanText(string $value, int $maximum): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, $maximum, 'UTF-8');
    }

    private function nullableText(?string $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = $this->cleanText($value, $maximum);

        return $clean !== '' ? $clean : null;
    }

    private function normalizeJobType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $normalized = mb_strtolower($type, 'UTF-8');
        foreach ([
            'full_time' => ['full time', 'full-time', 'penuh waktu'],
            'part_time' => ['part time', 'part-time', 'paruh waktu'],
            'contract' => ['contract', 'kontrak'],
            'internship' => ['intern', 'internship', 'magang'],
            'freelance' => ['freelance', 'lepas'],
        ] as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($normalized, $alias)) {
                    return $canonical;
                }
            }
        }

        return null;
    }
}
