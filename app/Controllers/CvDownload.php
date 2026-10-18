<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Pdf\DompdfRenderer;
use App\Services\CvRenderService;
use App\Services\CvService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

final class CvDownload extends BaseController
{
    public function download(string $cvId): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ((new CvService())->findOwned($user->id, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! service('throttler')->check('cv-pdf-' . $user->id, 5, 60)) {
            return $this->response
                ->setStatusCode(429)
                ->setHeader('Cache-Control', 'private, no-store')
                ->setBody(lang('CvBuilder.download_throttled'));
        }

        $renderer = new CvRenderService();
        $document = $renderer->buildDocument($user->id, $cvId);
        $rendered = (new DompdfRenderer())->renderWithPageCount(
            $renderer->renderDocumentHtml($document),
            (string) ($document['cv']['paper_size'] ?? 'a4'),
        );

        $filename = $this->safeFilename((string) ($document['cv']['title'] ?? 'CV')) . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Content-Length', (string) strlen($rendered->contents))
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($rendered->contents);
    }

    private function safeFilename(string $title): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($title));
        $filename = trim((string) $filename, '.-_');

        return substr($filename === '' ? 'CV' : $filename, 0, 80);
    }
}
