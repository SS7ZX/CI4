<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Dto;

final class CvSearchProfile
{
    /**
     * @param list<string> $targetTitles
     * @param list<string> $titleAliases
     * @param array<string, array{weight: int, occurrences: int, category: string}> $skills
     * @param list<string> $missingSections
     */
    public function __construct(
        public readonly array $targetTitles,
        public readonly array $titleAliases,
        public readonly array $skills,
        public readonly float $experienceYears,
        public readonly string $seniority,
        public readonly ?string $location,
        public readonly ?string $highestEducation,
        public readonly ?string $educationField,
        public readonly array $missingSections,
    ) {
    }

    public function isReady(): bool
    {
        return $this->targetTitles !== [] && ($this->skills !== [] || $this->location !== null);
    }

    /**
     * This serialization intentionally excludes all direct personal identifiers.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_titles'    => $this->targetTitles,
            'title_aliases'    => $this->titleAliases,
            'skills'           => $this->skills,
            'experience_years' => $this->experienceYears,
            'seniority'        => $this->seniority,
            'location'         => $this->location,
            'highest_education' => $this->highestEducation,
            'education_field'  => $this->educationField,
            'missing_sections' => $this->missingSections,
            'ready'            => $this->isReady(),
        ];
    }
}
