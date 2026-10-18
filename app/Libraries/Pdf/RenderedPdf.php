<?php

declare(strict_types=1);

namespace App\Libraries\Pdf;

final class RenderedPdf
{
    public function __construct(
        public readonly string $contents,
        public readonly int $pageCount,
    ) {
    }
}
