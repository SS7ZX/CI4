<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Dto;

final class ProviderResult
{
    /**
     * @param list<JobPostingDto> $postings
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly array $postings,
        public readonly ?int $totalCount,
        public readonly int $page,
        public readonly array $warnings = [],
    ) {
    }
}
