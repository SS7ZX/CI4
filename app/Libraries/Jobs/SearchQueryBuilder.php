<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Libraries\Jobs\Dto\SearchQueryDto;
use Config\JobMatching;

final class SearchQueryBuilder
{
    public function __construct(
        private readonly LocationSanitizer $locations = new LocationSanitizer(),
        private readonly ?JobMatching $config = null,
    ) {
    }

    /**
     * @param array<string, mixed> $preferences
     *
     * @return list<SearchQueryDto>
     */
    public function build(CvSearchProfile $profile, array $preferences): array
    {
        $config = $this->config ?? config('JobMatching');
        $titles = array_values(array_filter(
            is_array($preferences['target_titles'] ?? null) ? $preferences['target_titles'] : [],
            static fn (mixed $title): bool => is_string($title) && trim($title) !== '',
        ));
        $cities = array_values(array_filter(array_map(
            fn (mixed $city): ?string => is_string($city) ? $this->locations->normalizeCity($city) : null,
            is_array($preferences['locations'] ?? null) ? $preferences['locations'] : [],
        )));
        $skills = array_slice(array_keys($profile->skills), 0, 3);
        $queries = [];
        foreach ($titles as $title) {
            foreach ($cities as $city) {
                $keywords = trim($title . ($skills !== [] ? ' ' . implode(' ', $skills) : ''));
                $queries[] = new SearchQueryDto(
                    mb_substr($keywords, 0, 500, 'UTF-8'),
                    $city,
                );
                if (count($queries) >= $config->maxQueriesPerRefresh) {
                    return $queries;
                }
            }
        }

        return $queries;
    }
}
