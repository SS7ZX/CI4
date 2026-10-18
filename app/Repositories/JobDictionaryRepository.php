<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

final class JobDictionaryRepository
{
    public function __construct(private readonly ?BaseConnection $database = null)
    {
    }

    /**
     * @return list<array{canonical: string, aliases: list<string>, category: string, weight: int}>
     */
    public function skills(): array
    {
        $rows = ($this->database ?? db_connect())
            ->table('skills_dictionary')
            ->orderBy('canonical')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): array => [
            'canonical' => (string) $row['canonical'],
            'aliases'   => self::decodeList($row['aliases'] ?? null),
            'category'  => (string) $row['category'],
            'weight'    => (int) $row['weight'],
        ], $rows);
    }

    /**
     * @return list<array{canonical_title: string, aliases: list<string>, job_family: string, seniority_hint: string|null}>
     */
    public function titles(): array
    {
        $rows = ($this->database ?? db_connect())
            ->table('title_dictionary')
            ->orderBy('canonical_title')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): array => [
            'canonical_title' => (string) $row['canonical_title'],
            'aliases'         => self::decodeList($row['aliases'] ?? null),
            'job_family'      => (string) $row['job_family'],
            'seniority_hint'  => isset($row['seniority_hint']) ? (string) $row['seniority_hint'] : null,
        ], $rows);
    }

    /**
     * @return list<string>
     */
    private static function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
