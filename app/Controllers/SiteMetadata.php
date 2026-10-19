<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class SiteMetadata extends BaseController
{
    public function robots(): ResponseInterface
    {
        $sitemapUrl = $this->absoluteUrl('sitemap.xml');
        $contents = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /logout',
            'Sitemap: ' . $sitemapUrl,
            '',
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setBody($contents);
    }

    public function sitemap(): ResponseInterface
    {
        $homepage = htmlspecialchars($this->absoluteUrl(''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $contents = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>' . $homepage . '</loc></url>'
            . '</urlset>';

        return $this->response
            ->setHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->setHeader('X-Robots-Tag', 'noindex')
            ->setBody($contents);
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim(config('App')->baseURL, '/') . ($path === '' ? '/' : '/' . ltrim($path, '/'));
    }
}
