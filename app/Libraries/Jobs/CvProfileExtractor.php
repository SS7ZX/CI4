<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Libraries\Jobs\Dto\CvSearchProfile;
use DateTimeImmutable;

final class CvProfileExtractor
{
    public function __construct(
        private readonly SkillExtractor $skillExtractor = new SkillExtractor(),
        private readonly TitleMatcher $titleMatcher = new TitleMatcher(),
        private readonly SeniorityEstimator $seniorityEstimator = new SeniorityEstimator(),
        private readonly LocationSanitizer $locationSanitizer = new LocationSanitizer(),
    ) {
    }

    /**
     * @param array<string, mixed> $cvData Loaded CV and child records from CvBuilderService.
     * @param list<array{canonical: string, aliases: list<string>, category?: string, weight?: int}> $skillsDictionary
     * @param list<array{canonical_title: string, aliases: list<string>, job_family?: string, seniority_hint?: string|null}> $titleDictionary
     */
    public function extract(
        array $cvData,
        array $skillsDictionary,
        array $titleDictionary,
        ?DateTimeImmutable $asOf = null,
    ): CvSearchProfile {
        $profile = $this->toArray($cvData['profile'] ?? []);
        $experiences = array_map($this->toArray(...), $cvData['experiences'] ?? []);
        usort($experiences, static fn (array $left, array $right): int => strcmp(
            (string) ($right['start_date'] ?? ''),
            (string) ($left['start_date'] ?? ''),
        ));

        $targetTitles = [];
        $titleAliases = [];
        $titleInputs = array_filter([
            $profile['headline'] ?? null,
            ...array_slice(array_column($experiences, 'position'), 0, 3),
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
        foreach ($titleInputs as $titleInput) {
            $matched = $this->titleMatcher->match((string) $titleInput, $titleDictionary);
            if ($matched === null) {
                continue;
            }

            $targetTitles[$matched['canonical_title']] = true;
            foreach ($matched['aliases'] as $alias) {
                $titleAliases[$alias] = true;
            }
            $titleAliases[$matched['canonical_title']] = true;
        }

        $weightedSkills = [];
        $addSkills = function (string $text, int $boost = 0) use (&$weightedSkills, $skillsDictionary): void {
            foreach ($this->skillExtractor->extract($text, $skillsDictionary) as $canonical => $skill) {
                if (! isset($weightedSkills[$canonical])) {
                    $weightedSkills[$canonical] = [
                        'weight'      => 0,
                        'occurrences' => 0,
                        'category'    => $skill['category'],
                    ];
                }
                $weightedSkills[$canonical]['occurrences'] += $skill['occurrences'];
                $weightedSkills[$canonical]['weight'] += $skill['weight'] + $boost + max(0, $skill['occurrences'] - 1);
            }
        };

        foreach ($cvData['skill_groups'] ?? [] as $group) {
            $group = $this->toArray($group);
            $addSkills((string) ($group['name'] ?? ''));
            $addSkills(implode(' ', $this->stringList($group['items'] ?? [])));
        }
        foreach ($experiences as $index => $experience) {
            $addSkills(implode(' ', $this->stringList($experience['bullets'] ?? [])), $index < 3 ? 2 : 0);
        }
        foreach ($cvData['projects'] ?? [] as $project) {
            $project = $this->toArray($project);
            $addSkills(implode(' ', [
                (string) ($project['name'] ?? ''),
                ...$this->stringList($project['tech_stack'] ?? []),
                ...$this->stringList($project['bullets'] ?? []),
            ]));
        }
        foreach ($cvData['certifications'] ?? [] as $certification) {
            $certification = $this->toArray($certification);
            $addSkills(implode(' ', [
                (string) ($certification['name'] ?? ''),
                (string) ($certification['issuer'] ?? ''),
            ]));
        }
        uasort($weightedSkills, static fn (array $left, array $right): int => $right['weight'] <=> $left['weight']);

        $seniority = $this->seniorityEstimator->estimate($experiences, $asOf);
        $education = array_map($this->toArray(...), $cvData['educations'] ?? []);
        usort($education, fn (array $left, array $right): int => $this->degreeRank($right['degree'] ?? '') <=> $this->degreeRank($left['degree'] ?? ''));
        $highestEducation = $education[0]['degree'] ?? null;
        $educationField = $education[0]['field_of_study'] ?? null;

        $location = $this->locationSanitizer->normalizeCity((string) ($profile['city'] ?? ''));
        $missing = [];
        if ($targetTitles === []) {
            $missing[] = 'headline';
        }
        if ($weightedSkills === []) {
            $missing[] = 'skills';
        }
        if ($location === null) {
            $missing[] = 'city';
        }
        if ($experiences === []) {
            $missing[] = 'experience';
        }

        return new CvSearchProfile(
            array_keys($targetTitles),
            array_keys($titleAliases),
            $weightedSkills,
            $seniority['years'],
            $seniority['level'],
            $location,
            is_string($highestEducation) ? $highestEducation : null,
            is_string($educationField) ? $educationField : null,
            $missing,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value) && method_exists($value, 'toRawArray')) {
            return $value->toRawArray();
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $value),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private function degreeRank(mixed $degree): int
    {
        $degree = mb_strtolower((string) $degree, 'UTF-8');

        return match (true) {
            str_contains($degree, 'doktor'), str_contains($degree, 'phd'), str_contains($degree, 's3') => 5,
            str_contains($degree, 'master'), str_contains($degree, 'magister'), str_contains($degree, 's2') => 4,
            str_contains($degree, 'sarjana'), str_contains($degree, 'bachelor'), str_contains($degree, 's1') => 3,
            str_contains($degree, 'diploma iv'), str_contains($degree, 'd4') => 3,
            str_contains($degree, 'diploma iii'), str_contains($degree, 'd3') => 2,
            str_contains($degree, 'diploma'), str_contains($degree, 'd1'), str_contains($degree, 'd2') => 1,
            default => 0,
        };
    }
}
