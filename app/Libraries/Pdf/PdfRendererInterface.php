<?php

declare(strict_types=1);

namespace App\Libraries\Pdf;

interface PdfRendererInterface
{
    public function render(string $html, string $paperSize = 'a4'): string;

    public function renderWithPageCount(string $html, string $paperSize = 'a4'): RenderedPdf;
}
