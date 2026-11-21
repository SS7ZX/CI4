<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

final class CvAiDraft
{
    public function __construct(
        public readonly string $headline,
        public readonly string $summary,
        public readonly string $experience,
        public readonly string $education,
        public readonly string $skills,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'headline'   => $this->headline,
            'summary'    => $this->summary,
            'experience' => $this->experience,
            'education'  => $this->education,
            'skills'     => $this->skills,
        ];
    }
}
