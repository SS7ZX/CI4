<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class JobPreferenceRepository
{
    public function __construct(private readonly ?BaseConnection $database = null)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForCv(string $cvId): ?array
    {
        $row = ($this->database ?? db_connect())
            ->table('job_preferences')
            ->where('cv_id', $cvId)
            ->get()
            ->getRowArray();
        if ($row === null) {
            return null;
        }

        foreach (['target_titles', 'locations', 'job_types', 'excluded_keywords'] as $field) {
            $row[$field] = $this->decodeList($row[$field] ?? null);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(string $cvId, array $data): void
    {
        $database = $this->database ?? db_connect();
        $values   = $data + ['updated_at' => gmdate('Y-m-d H:i:s')];
        foreach (['target_titles', 'locations', 'job_types', 'excluded_keywords'] as $field) {
            $values[$field] = json_encode($values[$field], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $existing = $database->table('job_preferences')
            ->select('id')
            ->where('cv_id', $cvId)
            ->get()
            ->getRowArray();
        if ($existing === null) {
            $values['id']    = $this->uuid();
            $values['cv_id'] = $cvId;
            if (! $database->table('job_preferences')->insert($values)) {
                throw new RuntimeException('Preferensi lowongan tidak dapat disimpan.');
            }

            return;
        }

        if (! $database->table('job_preferences')->where('cv_id', $cvId)->update($values)) {
            throw new RuntimeException('Preferensi lowongan tidak dapat diperbarui.');
        }
    }

    public function deleteMatchesForCv(string $cvId): void
    {
        $database = $this->database ?? db_connect();
        if ($database->tableExists('job_application_trackers')) {
            $database->table('job_application_trackers')->where('cv_id', $cvId)->delete();
        }
        if ($database->tableExists('job_matches')) {
            $database->table('job_matches')->where('cv_id', $cvId)->delete();
        }
    }

    /**
     * @return list<string>
     */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
