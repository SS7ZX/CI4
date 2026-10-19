<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\JobMatching;
use App\Libraries\Jobs\Dto\CvSearchProfile;

final class SkillGapService
{
    public function __construct(
        private readonly ?JobMatching $config = null,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @param list<array{canonical: string, aliases: list<string>}> $skillsDictionary
     *
     * @return array{skills: list<array{skill: string, count: int}>, salary: array{sample_count: int, minimum: int|null, median_minimum: int|null, median_maximum: int|null, maximum: int|null, minimum_samples: int}}
     */
    public function analyze(CvSearchProfile $profile, array $matches, array $skillsDictionary = []): array
    {
        $config = $this->config ?? config('JobMatching');
        $canonicalByAlias = [];
        foreach ($skillsDictionary as $skill) {
            foreach ([$skill['canonical'], ...$skill['aliases']] as $alias) {
                $canonicalByAlias[mb_strtolower($alias, 'UTF-8')] = $skill['canonical'];
            }
        }
        $canonicalize = static fn (string $skill): string => $canonicalByAlias[mb_strtolower($skill, 'UTF-8')]
            ?? $skill;
        $topMatches = array_slice(
            array_values(array_filter(
                $matches,
                fn (array $match): bool => (int) ($match['score'] ?? 0) >= $config->minimumScoreForSkillGap
                    && ! (bool) ($match['is_hidden_by_score'] ?? false)
                    && ($match['status'] ?? '') !== 'hidden',
            )),
            0,
            max(1, $config->topJobsForSkillGap),
        );
        $profileSkills = array_fill_keys(
            array_map(
                static fn (string $skill): string => mb_strtolower($canonicalize($skill), 'UTF-8'),
                array_keys($profile->skills),
            ),
            true,
        );
        $frequency = [];
        $salaryMinimums = [];
        $salaryMaximums = [];

        foreach ($topMatches as $match) {
            foreach ($this->decodeList($match['missing_skills'] ?? []) as $skill) {
                $canonical = $canonicalize(trim($skill));
                $key = mb_strtolower($canonical, 'UTF-8');
                if ($key === '' || isset($profileSkills[$key])) {
                    continue;
                }
                $frequency[$canonical] = ($frequency[$canonical] ?? 0) + 1;
            }

            $minimum = filter_var($match['salary_min_idr'] ?? null, FILTER_VALIDATE_INT);
            $maximum = filter_var($match['salary_max_idr'] ?? null, FILTER_VALIDATE_INT);
            if (is_int($minimum) && $minimum > 0) {
                $salaryMinimums[] = $minimum;
            }
            if (is_int($maximum) && $maximum > 0) {
                $salaryMaximums[] = $maximum;
            }
        }

        arsort($frequency);
        $skills = [];
        foreach ($frequency as $skill => $count) {
            $skills[] = ['skill' => (string) $skill, 'count' => $count];
        }

        $salarySamples = count($salaryMinimums);
        $enoughSalarySamples = $salarySamples >= $config->minimumSalarySamplesForSummary;
        sort($salaryMinimums);
        sort($salaryMaximums);

        return [
            'skills' => $skills,
            'salary' => [
                'sample_count' => $salarySamples,
                'minimum' => $enoughSalarySamples ? min($salaryMinimums) : null,
                'median_minimum' => $enoughSalarySamples ? $this->median($salaryMinimums) : null,
                'median_maximum' => $enoughSalarySamples && count($salaryMaximums) >= $config->minimumSalarySamplesForSummary
                    ? $this->median($salaryMaximums)
                    : null,
                'maximum' => $enoughSalarySamples && count($salaryMaximums) >= $config->minimumSalarySamplesForSummary
                    ? max($salaryMaximums)
                    : null,
                'minimum_samples' => $config->minimumSalarySamplesForSummary,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * @param list<int> $values
     */
    private function median(array $values): int
    {
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? (int) round(($values[$middle - 1] + $values[$middle]) / 2)
            : $values[$middle];
    }
}
