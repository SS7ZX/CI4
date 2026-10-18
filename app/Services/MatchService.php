<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Libraries\Jobs\Dto\MatchResult;
use App\Libraries\Jobs\MatchScorer;
use App\Repositories\JobDictionaryRepository;
use App\Repositories\JobSearchRepository;
use DateTimeImmutable;

final class MatchService
{
    public function __construct(
        private readonly JobPreferenceService $preferences = new JobPreferenceService(),
        private readonly JobSearchRepository $matches = new JobSearchRepository(),
        private readonly JobDictionaryRepository $dictionaries = new JobDictionaryRepository(),
        private readonly MatchScorer $scorer = new MatchScorer(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $postings
     *
     * @return list<array<string, mixed>>
     */
    public function scoreAndStorePostings(int|string $userId, string $cvId, array $postings): array
    {
        $workflow = $this->preferences->workflow($userId, $cvId);
        if (! $workflow['hasConsent']) {
            throw new JobConsentRequiredException();
        }
        $dictionary = $this->dictionaries->skills();
        $titles = $this->dictionaries->titles();
        $scored = [];
        foreach ($postings as $posting) {
            $result = $this->scorer->score(
                $workflow['profile'],
                $posting,
                $workflow['preference'] ?? $workflow['suggestedPreferences'],
                $dictionary,
                $titles,
            );
            $this->matches->saveMatchResult($cvId, (string) $posting['id'], $result);
            $scored[] = [...$posting, ...$result->toArray()];
        }

        return $scored;
    }

    public function recomputeForCv(int|string $userId, string $cvId): int
    {
        $workflow = $this->preferences->workflow($userId, $cvId);
        if (! $workflow['hasConsent']) {
            return 0;
        }
        $postings = $this->matches->candidatePostingsForCv($cvId);
        if ($postings === []) {
            return 0;
        }

        return count($this->scoreAndStorePostings($userId, $cvId, $postings));
    }

    public function resultForPosting(
        CvSearchProfile $profile,
        array $posting,
        array $preferences,
        ?DateTimeImmutable $asOf = null,
    ): MatchResult {
        return $this->scorer->score(
            $profile,
            $posting,
            $preferences,
            $this->dictionaries->skills(),
            $this->dictionaries->titles(),
            $asOf,
        );
    }
}
