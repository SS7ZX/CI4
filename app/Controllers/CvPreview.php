<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Pdf\DompdfRenderer;
use App\Services\CvRenderService;
use App\Services\CvService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

final class CvPreview extends BaseController
{
    private readonly CvRenderService $renderer;

    public function __construct()
    {
        $this->renderer = new CvRenderService();
    }

    public function current(string $cvId): ResponseInterface
    {
        return $this->render($cvId, null);
    }

    public function theme(string $cvId, string $themeKey): ResponseInterface
    {
        return $this->render($cvId, $themeKey);
    }

    public function pageCount(string $cvId): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ((new CvService())->findOwned($user->id, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! service('throttler')->check('cv-pages-' . $user->id, 12, 60)) {
            return $this->response->setStatusCode(429)->setJSON([
                'status' => 'error',
                'message' => lang('CvBuilder.page_count_error'),
            ]);
        }

        $document = $this->renderer->buildDocument($user->id, $cvId);
        $pdf = (new DompdfRenderer())->renderWithPageCount(
            $this->renderer->renderDocumentHtml($document),
            (string) ($document['cv']['paper_size'] ?? 'a4'),
        );

        return $this->response
            ->setHeader('Cache-Control', 'private, no-store')
            ->setJSON(['status' => 'ok', 'page_count' => $pdf->pageCount]);
    }

    private function render(string $cvId, ?string $themeKey): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', 'text/html; charset=UTF-8')
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src 'none'; font-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'")
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($this->renderer->renderHtml($user->id, $cvId, $themeKey));
    }
}
