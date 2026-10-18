<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use App\Libraries\Jobs\Dto\MatchResult;
use App\Services\JobConsentRequiredException;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class JobSearchRepository
{
    public function __construct(private readonly ?BaseConnection $database = null)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCache(string $queryHash, bool $includeExpired = false): ?array
    {
        $builder = $this->db()->table('job_query_cache')->where('query_hash', $queryHash);
        if (! $includeExpired) {
            $builder->where('expires_at >', gmdate('Y-m-d H:i:s'));
        }
        $row = $builder->get()->getRowArray();
        if ($row === null) {
            return null;
        }
        $ids = is_array($row['posting_ids'] ?? null)
            ? $row['posting_ids']
            : json_decode((string) ($row['posting_ids'] ?? '[]'), true);
        $row['posting_ids'] = is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];

        return $row;
    }

    /**
     * @param list<string> $postingIds
     */
    public function saveCache(
        string $queryHash,
        string $provider,
        string $keywords,
        string $location,
        int $page,
        ?int $totalCount,
        array $postingIds,
        int $ttlSeconds,
    ): void {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $data = [
            'id' => $this->uuid(),
            'query_hash' => $queryHash,
            'provider' => $provider,
            'keywords' => $keywords,
            'location' => $location,
            'page' => $page,
            'total_count' => $totalCount,
            'posting_ids' => json_encode(array_values($postingIds), JSON_THROW_ON_ERROR),
            'fetched_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $now->modify('+' . max(0, $ttlSeconds) . ' seconds')->format('Y-m-d H:i:s'),
        ];
        $result = $this->db()->table('job_query_cache')
            ->onConstraint('query_hash')
            ->updateFields(['provider', 'keywords', 'location', 'page', 'total_count', 'posting_ids', 'fetched_at', 'expires_at'])
            ->upsert($data);
        if ($result === false) {
            throw new RuntimeException('Cache pencarian lowongan tidak dapat diperbarui.');
        }
    }

    /**
     * @param array<string, mixed> $posting
     *
     * @return array{id: string, inserted: bool}
     */
    public function upsertPosting(array $posting): array
    {
        $builder = $this->db()->table('job_postings');
        $existing = $builder->select('id')->where('dedupe_hash', $posting['dedupe_hash'])->get()->getRowArray();
        $insertId = $this->uuid();
        $updateFields = array_values(array_diff(array_keys($posting), ['dedupe_hash']));
        $result = $this->db()->table('job_postings')
            ->onConstraint('dedupe_hash')
            ->updateFields($updateFields)
            ->upsert(['id' => $insertId, ...$posting]);
        if ($result === false) {
            throw new RuntimeException('Lowongan tidak dapat diperbarui.');
        }
        $stored = $this->db()->table('job_postings')
            ->select('id')
            ->where('dedupe_hash', $posting['dedupe_hash'])
            ->get()
            ->getRowArray();
        if ($stored === null) {
            throw new RuntimeException('Lowongan tersimpan tanpa ID yang dapat dibaca.');
        }

        return ['id' => (string) $stored['id'], 'inserted' => $existing === null];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function postingsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db()->table('job_postings')
            ->whereIn('id', $ids)
            ->where('expires_at >', gmdate('Y-m-d H:i:s'))
            ->get()
            ->getResultArray();
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['id']] = $row;
        }

        return array_values(array_filter(
            array_map(static fn (string $id): ?array => $indexed[$id] ?? null, $ids),
        ));
    }

    public function startRefresh(string $cvId): string
    {
        $id = $this->uuid();
        if (! $this->db()->table('job_refresh_log')->insert([
            'id' => $id,
            'cv_id' => $cvId,
            'started_at' => gmdate('Y-m-d H:i:s'),
            'queries_run' => 0,
            'postings_new' => 0,
            'postings_updated' => 0,
        ])) {
            throw new RuntimeException('Pencatatan pencarian lowongan tidak dapat dimulai.');
        }

        return $id;
    }

    /**
     * @param array{queries_run: int, postings_new: int, postings_updated: int, error: ?string} $summary
     */
    public function finishRefresh(string $id, array $summary): void
    {
        if (! $this->db()->table('job_refresh_log')->where('id', $id)->update([
            ...$summary,
            'finished_at' => gmdate('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('Pencatatan pencarian lowongan tidak dapat diselesaikan.');
        }
    }

    public function dailySearchCount(int|string $userId): int
    {
        return (int) $this->db()->table('job_refresh_log logs')
            ->join('cvs', 'cvs.id = logs.cv_id')
            ->where('cvs.user_id', $userId)
            ->where(
                'logs.started_at >=',
                (new DateTimeImmutable('today', new DateTimeZone(config('App')->appTimezone)))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s'),
            )
            ->countAllResults();
    }

    public function latestRefreshForCv(string $cvId): ?string
    {
        $row = $this->db()->table('job_refresh_log')
            ->select('started_at')
            ->where('cv_id', $cvId)
            ->orderBy('started_at', 'DESC')
            ->get(1)
            ->getRowArray();

        return is_string($row['started_at'] ?? null) ? $row['started_at'] : null;
    }

    /**
     * @param list<string> $postingIds
     */
    public function replaceMatchesForCv(string $cvId, array $postingIds): bool
    {
        $database = $this->db();
        $database->transBegin();
        try {
            if ($database->DBDriver === 'Postgre') {
                $preferencesTable = 'public.' . $database->protectIdentifiers(
                    $database->prefixTable('job_preferences'),
                    false,
                    true,
                );
                $preference = $database->query(
                    'SELECT search_enabled, consent_at FROM ' . $preferencesTable . ' WHERE cv_id = ? FOR UPDATE',
                    [$cvId],
                )->getRowArray();
            } else {
                $preference = $database->table('job_preferences')
                    ->select('search_enabled, consent_at')
                    ->where('cv_id', $cvId)
                    ->get()
                    ->getRowArray();
            }
            if (
                $preference === null
                || ! (bool) $preference['search_enabled']
                || empty($preference['consent_at'])
            ) {
                $database->transRollback();

                return false;
            }

            $database->table('job_matches')->where('cv_id', $cvId)->delete();
            foreach (array_values(array_unique($postingIds)) as $postingId) {
                if (! $database->table('job_matches')->insert([
                    'id' => $this->uuid(),
                    'cv_id' => $cvId,
                    'job_posting_id' => $postingId,
                    'matched_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('Hasil pencarian untuk CV tidak dapat disimpan.');
                }
            }
            $database->transCommit();

            return true;
        } catch (\Throwable $exception) {
            $database->transRollback();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $posting
     */
    public function saveMatchResult(string $cvId, string $postingId, MatchResult $result): void
    {
        $database = $this->db();
        $database->transBegin();
        try {
            if ($database->DBDriver === 'Postgre') {
                $preferencesTable = 'public.' . $database->protectIdentifiers(
                    $database->prefixTable('job_preferences'),
                    false,
                    true,
                );
                $preference = $database->query(
                    'SELECT search_enabled, consent_at FROM ' . $preferencesTable . ' WHERE cv_id = ? FOR UPDATE',
                    [$cvId],
                )->getRowArray();
            } else {
                $preference = $database->table('job_preferences')
                    ->select('search_enabled, consent_at')
                    ->where('cv_id', $cvId)
                    ->get()
                    ->getRowArray();
            }
            if (
                $preference === null
                || ! (bool) $preference['search_enabled']
                || empty($preference['consent_at'])
            ) {
                throw new JobConsentRequiredException();
            }

            $data = [
                'id' => $this->uuid(),
                'cv_id' => $cvId,
                'job_posting_id' => $postingId,
                'matched_at' => gmdate('Y-m-d H:i:s'),
                'score' => $result->score,
                'label' => $result->label,
                'confidence' => $result->confidence,
                'breakdown' => json_encode($result->breakdown, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'matched_skills' => json_encode($result->matchedSkills, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'missing_skills' => json_encode($result->missingSkills, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'reasons' => json_encode($result->reasons, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'status' => 'new',
                'notes' => null,
                'status_changed_at' => gmdate('Y-m-d H:i:s'),
                'computed_at' => gmdate('Y-m-d H:i:s'),
                'excluded_keyword_match' => $result->excludedKeywordMatch,
                'is_hidden_by_score' => $result->hidden,
            ];
            $saved = $database->table('job_matches')
                ->onConstraint(['cv_id', 'job_posting_id'])
                ->updateFields([
                    'score',
                    'label',
                    'confidence',
                    'breakdown',
                    'matched_skills',
                    'missing_skills',
                    'reasons',
                    'computed_at',
                    'excluded_keyword_match',
                    'is_hidden_by_score',
                ])
                ->upsert($data);
            if ($saved === false || $database->transStatus() === false) {
                throw new RuntimeException('Hasil kecocokan lowongan tidak dapat disimpan.');
            }
            $database->transCommit();
        } catch (\Throwable $exception) {
            $database->transRollback();

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function candidatePostingsForCv(string $cvId): array
    {
        return $this->db()->table('job_matches')
            ->select('job_postings.*, job_matches.id AS match_id, job_matches.status, job_matches.notes')
            ->join('job_postings', 'job_postings.id = job_matches.job_posting_id')
            ->where('job_matches.cv_id', $cvId)
            ->where('job_matches.is_hidden_by_score', false)
            ->where('job_postings.expires_at >', gmdate('Y-m-d H:i:s'))
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function matchesForCv(string $cvId): array
    {
        return $this->db()->table('job_matches')
            ->select('job_postings.*, job_matches.id AS match_id, job_matches.matched_at, job_matches.score, job_matches.label, job_matches.confidence, job_matches.breakdown, job_matches.matched_skills, job_matches.missing_skills, job_matches.reasons, job_matches.status, job_matches.notes, job_matches.excluded_keyword_match, job_matches.is_hidden_by_score')
            ->join('job_postings', 'job_postings.id = job_matches.job_posting_id')
            ->where('job_matches.cv_id', $cvId)
            ->where('job_postings.expires_at >', gmdate('Y-m-d H:i:s'))
            ->orderBy('job_matches.score', 'DESC')
            ->orderBy('job_postings.posted_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array{status?: string, location?: string, job_type?: string, remote?: string, min_score?: int} $filters
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function paginatedMatchesForCv(
        string $cvId,
        array $filters,
        string $sort,
        int $page,
        int $perPage = 10,
    ): array {
        $builder = $this->matchListBuilder($cvId, $filters);
        $total = (clone $builder)->countAllResults();
        $perPage = max(1, min(50, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($lastPage, max(1, $page));

        match ($sort) {
            'recent' => $builder->orderBy('job_postings.posted_at', 'DESC'),
            'salary' => $builder->orderBy('job_postings.salary_min_idr', 'DESC'),
            default => $builder->orderBy('job_matches.score', 'DESC'),
        };
        $rows = $builder
            ->orderBy('job_postings.posted_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function matchForCv(string $cvId, string $matchId): ?array
    {
        $row = $this->db()->table('job_matches')
            ->select('job_postings.*, job_matches.id AS match_id, job_matches.cv_id, job_matches.matched_at, job_matches.score, job_matches.label, job_matches.confidence, job_matches.breakdown, job_matches.matched_skills, job_matches.missing_skills, job_matches.reasons, job_matches.status, job_matches.notes, job_matches.excluded_keyword_match, job_matches.is_hidden_by_score')
            ->join('job_postings', 'job_postings.id = job_matches.job_posting_id')
            ->where('job_matches.cv_id', $cvId)
            ->where('job_matches.id', $matchId)
            ->where('job_postings.expires_at >', gmdate('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        return $row === null ? null : $row;
    }

    public function updateMatchStatus(string $cvId, string $matchId, string $status): bool
    {
        if (! in_array($status, ['new', 'saved', 'hidden'], true)) {
            throw new RuntimeException('Status hasil lowongan tidak valid.');
        }

        return $this->db()->table('job_matches')
            ->where('cv_id', $cvId)
            ->where('id', $matchId)
            ->update([
                'status' => $status,
                'status_changed_at' => gmdate('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @param array{status?: string, location?: string, job_type?: string, remote?: string, min_score?: int} $filters
     */
    private function matchListBuilder(string $cvId, array $filters): \CodeIgniter\Database\BaseBuilder
    {
        $builder = $this->db()->table('job_matches')
            ->select('job_postings.*, job_matches.id AS match_id, job_matches.matched_at, job_matches.score, job_matches.label, job_matches.confidence, job_matches.breakdown, job_matches.matched_skills, job_matches.missing_skills, job_matches.reasons, job_matches.status, job_matches.notes, job_matches.excluded_keyword_match, job_matches.is_hidden_by_score')
            ->join('job_postings', 'job_postings.id = job_matches.job_posting_id')
            ->where('job_matches.cv_id', $cvId)
            ->where('job_postings.expires_at >', gmdate('Y-m-d H:i:s'));

        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $builder->where('job_matches.status !=', 'hidden');
        } elseif ($status !== 'all') {
            $builder->where('job_matches.status', $status);
        }
        if (($filters['location'] ?? '') !== '') {
            $builder->where('job_postings.location', $filters['location']);
        }
        if (($filters['job_type'] ?? '') !== '') {
            $builder->where('job_postings.job_type', $filters['job_type']);
        }
        if (($filters['remote'] ?? '') === 'remote') {
            $builder->where('job_postings.is_remote', true);
        } elseif (($filters['remote'] ?? '') === 'onsite') {
            $builder->groupStart()
                ->where('job_postings.is_remote', false)
                ->orWhere('job_postings.is_remote', null)
                ->groupEnd();
        }
        if (($filters['min_score'] ?? 0) > 0) {
            $builder->where('job_matches.score >=', $filters['min_score']);
        }

        return $builder;
    }

    public function deleteExpired(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $postings = $this->db()->table('job_postings')->where('expires_at <=', $now)->delete();
        $postingCount = $this->db()->affectedRows();
        $this->db()->table('job_query_cache')->where('expires_at <=', $now)->delete();
        $cacheCount = $this->db()->affectedRows();

        return ['postings' => $postingCount, 'cache' => $cacheCount];
    }

    public function deleteOldCache(int $olderThanSeconds): int
    {
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . max(0, $olderThanSeconds) . ' seconds')
            ->format('Y-m-d H:i:s');
        $this->db()->table('job_query_cache')->where('fetched_at <', $cutoff)->delete();

        return $this->db()->affectedRows();
    }

    private function db(): BaseConnection
    {
        return $this->database ?? db_connect();
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
