<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Dto;

final class SalaryRange
{
    public function __construct(
        public readonly ?int $minimumIdr,
        public readonly ?int $maximumIdr,
        public readonly ?string $currency,
        public readonly bool $comparable,
    ) {
    }
}
