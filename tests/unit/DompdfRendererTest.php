<?php

declare(strict_types=1);

use App\Libraries\Pdf\DompdfRenderer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DompdfRendererTest extends CIUnitTestCase
{
    public function testRendererRegistersAndEmbedsLocalCvmakerFont(): void
    {
        $pdf = (new DompdfRenderer())->render(
            '<!doctype html><html><head><style>body { font-family: "CVMaker Sans"; }</style></head><body>CV Maker</body></html>',
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }
}
