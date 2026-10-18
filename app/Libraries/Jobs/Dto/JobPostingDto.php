<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Dto;

final class JobPostingDto
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?string $externalKey,
        public readonly string $title,
        public readonly ?string $company,
        public readonly ?string $location,
        public readonly string $snippet,
        public readonly ?string $salaryText,
        public readonly ?string $sourceName,
        public readonly ?string $jobType,
        public readonly string $link,
        public readonly ?string $updatedAt,
        public readonly array $raw = [],
    ) {
    }

}
