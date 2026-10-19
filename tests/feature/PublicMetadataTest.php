<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * @internal
 */
final class PublicMetadataTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testRobotsAndSitemapExposeOnlyTheConfiguredPublicHomepage(): void
    {
        $robots = $this->get('/robots.txt');
        $robots->assertOK();
        $this->assertStringContainsString('text/plain', $robots->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Disallow: /dashboard', $robots->getBody());
        $this->assertStringContainsString('Sitemap: http://example.com/sitemap.xml', $robots->getBody());

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertOK();
        $this->assertStringContainsString('application/xml', $sitemap->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<loc>http://example.com/</loc>', $sitemap->getBody());
        $this->assertStringNotContainsString('/dashboard', $sitemap->getBody());
        $this->assertStringNotContainsString('/login', $sitemap->getBody());
    }

    public function testUnknownRouteUsesCustomIndonesianNotFoundPage(): void
    {
        try {
            $this->get('/alamat-yang-tidak-ada');
            $this->fail('Rute yang tidak dikenal harus menghasilkan 404.');
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        }

        $html = view('errors/html/error_404', ['message' => 'detail internal']);
        $this->assertStringContainsString('Kesalahan 404', $html);
        $this->assertStringContainsString('Alamat yang Anda buka tidak tersedia atau sudah berubah.', $html);
        $this->assertStringContainsString('Kembali ke beranda', $html);
        $this->assertStringNotContainsString('detail internal', $html);
    }

    public function testProductionErrorPageHidesExceptionDetails(): void
    {
        $html = view('errors/html/production');

        $this->assertStringContainsString('Kesalahan 500', $html);
        $this->assertStringContainsString('Permintaan belum dapat diproses.', $html);
        $this->assertStringNotContainsString('Whoops', $html);
    }
}
