<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Dto;

final class MatchResult
{
    /**
     * @param array<string, array{score: float, weight: int, normalized_weight?: float, reason: string}> $breakdown
     * @param list<string> $matchedSkills
     * @param list<string> $missingSkills
     * @param list<string> $reasons
     */
    public function __construct(
        public readonly int $score,
        public readonly string $label,
        public readonly string $confidence,
        public readonly array $breakdown,
        public readonly array $matchedSkills,
        public readonly array $missingSkills,
        public readonly array $reasons,
        public readonly bool $excludedKeywordMatch,
        public readonly bool $hidden,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'label' => $this->label,
            'confidence' => $this->confidence,
            'breakdown' => $this->breakdown,
            'matched_skills' => $this->matchedSkills,
            'missing_skills' => $this->missingSkills,
            'reasons' => $this->reasons,
            'excluded_keyword_match' => $this->excludedKeywordMatch,
            'is_hidden_by_score' => $this->hidden,
        ];
    }
}
