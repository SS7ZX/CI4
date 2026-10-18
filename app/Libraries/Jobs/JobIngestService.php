<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Repositories\JobSearchRepository;
use App\Services\JobConsentRequiredException;
use App\Services\MatchService;
use App\Services\JobPreferenceService;
use Config\JobMatching;
use DateTimeImmutable;
use DateTimeZone;

final class JobIngestService
{
    public function __construct(
        private readonly JobPreferenceService $preferences = new JobPreferenceService(),
        private readonly JobProviderRegistry $providers = new JobProviderRegistry(),
        private readonly JobSearchRepository $repository = new JobSearchRepository(),
        private readonly SearchQueryBuilder $queryBuilder = new SearchQueryBuilder(),
        private readonly JobNormalizer $normalizer = new JobNormalizer(),
        private readonly ?JobMatching $config = null,
    ) {
    }

    /**
     * @return array{queries: int, postings: list<array<string, mixed>>, inserted: int, updated: int, cached: int, warnings: list<string>}
     */
    public function refresh(int|string $userId, string $cvId): array
    {
        $workflow = $this->preferences->workflow($userId, $cvId);
        if (! $workflow['hasConsent']) {
            throw new JobConsentRequiredException();
        }
        $provider = $this->providers->default();
        $config = $this->config ?? config('JobMatching');
        if ($this->repository->dailySearchCount($userId) >= $config->dailySearchLimit) {
            throw new JobSearchLimitException('Batas pencarian harian tercapai.');
        }
        $lastRefresh = $this->repository->latestRefreshForCv($cvId);
        if ($lastRefresh !== null) {
            $lastRefreshAt = new DateTimeImmutable($lastRefresh, new DateTimeZone('UTC'));
            if (time() - $lastRefreshAt->getTimestamp() < $config->cvRefreshCooldownSeconds) {
                throw new JobSearchLimitException('Tunggu sebelum memperbarui pencarian lowongan ini.');
            }
        }

        $queries = $this->queryBuilder->build($workflow['profile'], $workflow['searchPreferences']);
        if ($queries === []) {
            throw new JobSearchLimitException('Profil CV belum memiliki jabatan dan kota pencarian yang valid.');
        }
        $refreshId = $this->repository->startRefresh($cvId);
        $summary = ['queries_run' => 0, 'postings_new' => 0, 'postings_updated' => 0, 'error' => null];
        $postingIds = [];
        $warnings = [];
        $cachedCount = 0;

        try {
            foreach ($queries as $query) {
                $queryHash = hash('sha256', implode("\n", [
                    $provider->name(),
                    mb_strtolower($query->keywords, 'UTF-8'),
                    mb_strtolower($query->location, 'UTF-8'),
                    (string) $query->page,
                ]));
                $validCache = $this->repository->findCache($queryHash);
                if ($validCache !== null) {
                    $cachedCount++;
                    $postingIds = [...$postingIds, ...$validCache['posting_ids']];
                    continue;
                }

                $staleCache = $this->repository->findCache($queryHash, true);
                $summary['queries_run']++;
                try {
                    $result = $provider->search($query);
                } catch (JobProviderException) {
                    $warnings[] = 'Penyedia lowongan tidak dapat dihubungi untuk salah satu pencarian.';
                    if ($staleCache !== null) {
                        $postingIds = [...$postingIds, ...$staleCache['posting_ids']];
                        $cachedCount++;
                    }
                    log_message('warning', 'Pencarian penyedia lowongan gagal.');
                    continue;
                }

                $idsForQuery = [];
                foreach ($result->postings as $posting) {
                    $normalized = $this->normalizer->normalize(
                        $posting,
                        $provider->name(),
                        $config->postingRetentionSeconds,
                    );
                    if ($normalized === null) {
                        $warnings[] = 'Satu lowongan dilewati karena data penting tidak valid.';
                        continue;
                    }
                    $upsert = $this->repository->upsertPosting($normalized);
                    $idsForQuery[] = $upsert['id'];
                    $postingIds[] = $upsert['id'];
                    $summary[$upsert['inserted'] ? 'postings_new' : 'postings_updated']++;
                }
                $idsForQuery = array_values(array_unique($idsForQuery));
                $this->repository->saveCache(
                    $queryHash,
                    $provider->name(),
                    $query->keywords,
                    $query->location,
                    $query->page,
                    $result->totalCount,
                    $idsForQuery,
                    $config->queryCacheTtlSeconds,
                );
                $warnings = [...$warnings, ...$result->warnings];
            }
        } finally {
            $summary['error'] = $warnings !== [] ? implode(' ', array_values(array_unique($warnings))) : null;
            $this->repository->finishRefresh($refreshId, $summary);
        }

        $postingIds = array_values(array_unique($postingIds));
        $postings = $this->repository->postingsByIds(array_values(array_unique($postingIds)));
        $scoredPostings = (new MatchService(
            preferences: $this->preferences,
            matches: $this->repository,
        ))->scoreAndStorePostings($userId, $cvId, $postings);

        return [
            'queries' => $summary['queries_run'],
            'postings' => $scoredPostings,
            'inserted' => $summary['postings_new'],
            'updated' => $summary['postings_updated'],
            'cached' => $cachedCount,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
