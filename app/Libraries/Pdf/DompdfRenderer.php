<?php

declare(strict_types=1);

namespace App\Libraries\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class DompdfRenderer implements PdfRendererInterface
{
    private const FONT_STYLES = [
        ['weight' => 'normal', 'style' => 'normal', 'file' => 'DejaVuSans.ttf'],
        ['weight' => 'bold', 'style' => 'normal', 'file' => 'DejaVuSans-Bold.ttf'],
        ['weight' => 'normal', 'style' => 'italic', 'file' => 'DejaVuSans-Oblique.ttf'],
        ['weight' => 'bold', 'style' => 'italic', 'file' => 'DejaVuSans-BoldOblique.ttf'],
    ];

    public function render(string $html, string $paperSize = 'a4'): string
    {
        return $this->renderWithPageCount($html, $paperSize)->contents;
    }

    public function renderWithPageCount(string $html, string $paperSize = 'a4'): RenderedPdf
    {
        if (! in_array($paperSize, ['a4', 'letter'], true)) {
            throw new RuntimeException('Ukuran kertas PDF tidak valid.');
        }

        $fontDirectory  = WRITEPATH . 'fonts' . DIRECTORY_SEPARATOR . 'dompdf';
        $cacheDirectory = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'dompdf';
        foreach ([$fontDirectory, $cacheDirectory] as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Direktori font PDF tidak dapat disiapkan.');
            }
        }

        $vendorFontDirectory = ROOTPATH . 'vendor' . DIRECTORY_SEPARATOR . 'dompdf' . DIRECTORY_SEPARATOR . 'dompdf' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'fonts';
        $options = new Options();
        $options->set('defaultFont', 'CVMaker Sans');
        $options->set('fontDir', $fontDirectory);
        $options->set('fontCache', $cacheDirectory);
        $options->set('tempDir', WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'dompdf');
        $options->set('chroot', ROOTPATH);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultMediaType', 'print');
        $options->setAllowedProtocols(['file://']);

        $dompdf = new Dompdf($options);
        foreach (self::FONT_STYLES as $style) {
            $fontFile = $vendorFontDirectory . DIRECTORY_SEPARATOR . $style['file'];
            if (! is_file($fontFile)) {
                throw new RuntimeException('Font bawaan Dompdf tidak ditemukan: ' . $style['file']);
            }

            $registered = $dompdf->getFontMetrics()->registerFont([
                'family' => 'CVMaker Sans',
                'weight' => $style['weight'],
                'style'  => $style['style'],
            ], 'file://' . str_replace('\\', '/', $fontFile));
            if (! $registered) {
                throw new RuntimeException('Font CV Maker tidak dapat didaftarkan: ' . $style['file']);
            }
        }

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paperSize);
        $dompdf->render();

        return new RenderedPdf($dompdf->output(), $dompdf->getCanvas()->get_page_count());
    }
}
