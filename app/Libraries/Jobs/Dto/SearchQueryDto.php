<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Dto;

final class SearchQueryDto
{
    public function __construct(
        public readonly string $keywords,
        public readonly string $location,
        public readonly int $page = 1,
    ) {
        if ($keywords === '' || $location === '' || $page < 1) {
            throw new \InvalidArgumentException('Query lowongan harus berisi kata kunci, kota, dan halaman yang valid.');
        }
    }
}
