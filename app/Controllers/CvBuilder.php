<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Photos\LocalPhotoStorage;
use App\Libraries\Photos\PhotoStorageInterface;
use App\Services\CvBuilderService;
use App\Services\MatchService;
use App\Services\CvValidationException;
use App\Services\ThemeRegistry;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

final class CvBuilder extends BaseController
{
    private CvBuilderService $builder;
    private PhotoStorageInterface $photoStorage;
    private ThemeRegistry $themes;

    public function __construct()
    {
        $this->themes       = new ThemeRegistry();
        $this->builder      = new CvBuilderService(themeRegistry: $this->themes);
        $this->photoStorage = new LocalPhotoStorage();
    }

    public function edit(string $cvId, string $step = '1'): string
    {
        $stepNumber = filter_var($step, FILTER_VALIDATE_INT);
        if ($stepNumber === false || $stepNumber < 1 || $stepNumber > 8) {
            throw PageNotFoundException::forPageNotFound();
        }

        $data  = $this->builder->load($this->currentUser()->id, $cvId);
        $state = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $state[$key] = array_map(
                    static fn (mixed $entity): array => $entity->toRawArray(),
                    $value,
                );
            } elseif (is_object($value) && method_exists($value, 'toRawArray')) {
                $state[$key] = $value->toRawArray();
            } else {
                $state[$key] = $value;
            }
        }
        $state['cv']['id'] = $cvId;
        $sectionLabels = [];
        foreach ([
            'profile'         => 'full_name',
            'links'           => 'links',
            'summary'         => 'summary_label',
            'experience'      => 'experiences',
            'education'       => 'educations',
            'skills'          => 'skills',
            'projects'        => 'projects',
            'certifications'  => 'certifications',
            'organizations'   => 'organizations',
            'awards'          => 'awards',
            'languages'       => 'languages',
            'custom_sections' => 'custom_sections',
        ] as $sectionKey => $labelKey) {
            $sectionLabels[$sectionKey] = lang('CvBuilder.' . $labelKey);
        }

        return view('builder/edit', [
            'title'      => $state['cv']['title'],
            'cvId'       => $cvId,
            'step'       => $stepNumber,
            'state'      => $state,
            'saveUrl'    => url_to('cv-autosave', $cvId, $stepNumber),
            'photoUrl'   => url_to('cv-photo-upload', $cvId),
            'photoView'  => url_to('cv-photo', $cvId),
            'themePreviewUrl' => url_to('cv-theme-preview', $cvId, '__theme__'),
            'previewUrl' => url_to('cv-preview', $cvId),
            'pageCountUrl' => url_to('cv-page-count', $cvId),
            'downloadUrl' => url_to('cv-download', $cvId),
            'themes'     => $this->themes->all(),
            'sectionLabels' => $sectionLabels,
            'messages' => [
                'pageCountLoading' => lang('CvBuilder.page_count_loading'),
                'pageCountError' => lang('CvBuilder.page_count_error'),
                'pageCount' => lang('CvBuilder.page_count'),
            ],
            'tokenName'  => csrf_token(),
            'tokenValue' => csrf_hash(),
        ]);
    }

    public function autosave(string $cvId, string $step): ResponseInterface
    {
        $stepNumber = filter_var($step, FILTER_VALIDATE_INT);
        $payload    = $this->request->getJSON(true);
        if ($stepNumber === false || ! is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'error'  => lang('CvBuilder.invalid_request'),
                'csrf'   => csrf_hash(),
            ]);
        }

        $userId = $this->currentUser()->id;
        if (! service('throttler')->check('cv-autosave-' . $userId, 60, 60)) {
            return $this->response->setStatusCode(429)->setJSON([
                'status' => 'error',
                'error' => lang('CvBuilder.rate_limited'),
                'csrf' => csrf_hash(),
            ]);
        }

        try {
            $this->builder->saveStep($userId, $cvId, $stepNumber, $payload);
            if ($stepNumber <= 6) {
                try {
                    (new MatchService())->recomputeForCv($userId, $cvId);
                } catch (\RuntimeException) {
                    log_message('error', 'Skor kecocokan lowongan tidak dapat diperbarui setelah CV disimpan.');

                    return $this->response->setStatusCode(503)->setJSON([
                        'status' => 'saved_with_scoring_error',
                        'message' => lang('CvBuilder.saved_scoring_error'),
                        'csrf' => csrf_hash(),
                    ]);
                }
            }

            return $this->response->setJSON([
                'status' => 'saved',
                'message'=> lang('CvBuilder.saved'),
                'csrf'   => csrf_hash(),
            ]);
        } catch (CvValidationException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'invalid',
                'errors' => $exception->errors(),
                'csrf'   => csrf_hash(),
            ]);
        }
    }

    public function uploadPhoto(string $cvId): ResponseInterface
    {
        $userId = $this->currentUser()->id;
        $this->builder->findOwned($userId, $cvId)
            ?? throw PageNotFoundException::forPageNotFound();
        if (! service('throttler')->check('cv-photo-' . $userId, 10, 60)) {
            return $this->response->setStatusCode(429)->setJSON([
                'status' => 'error',
                'error' => lang('CvBuilder.rate_limited'),
                'csrf' => csrf_hash(),
            ]);
        }

        $photo = $this->request->getFile('photo');
        if ($photo === null) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'invalid',
                'errors' => ['photo' => lang('CvBuilder.photo_required')],
                'csrf'   => csrf_hash(),
            ]);
        }

        try {
            $newPath = $this->photoStorage->store($photo, $cvId);
            try {
                $oldPath = $this->builder->updatePhotoPath($userId, $cvId, $newPath);
            } catch (\Throwable $exception) {
                try {
                    $this->photoStorage->delete($newPath);
                } catch (\Throwable $cleanupException) {
                    log_message('error', 'Gagal membersihkan foto CV setelah penyimpanan database gagal: {message}', [
                        'message' => $cleanupException->getMessage(),
                    ]);
                }

                throw $exception;
            }
            if ($oldPath !== null) {
                $this->photoStorage->delete($oldPath);
            }

            return $this->response->setJSON([
                'status' => 'saved',
                'message'=> lang('CvBuilder.photo_saved'),
                'url'    => url_to('cv-photo', $cvId),
                'csrf'   => csrf_hash(),
            ]);
        } catch (CvValidationException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'invalid',
                'errors' => $exception->errors(),
                'csrf'   => csrf_hash(),
            ]);
        } catch (RuntimeException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'invalid',
                'errors' => ['photo' => $exception->getMessage()],
                'csrf'   => csrf_hash(),
            ]);
        }
    }

    public function photo(string $cvId): ResponseInterface
    {
        $photoPath = $this->builder->photoPath($this->currentUser()->id, $cvId);
        $photo     = $this->photoStorage->read($photoPath);

        return $this->response
            ->setHeader('Content-Type', $photo['mime'])
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($photo['contents']);
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }
}
