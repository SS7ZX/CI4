<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Photos\LocalPhotoStorage;
use App\Libraries\Photos\PhotoStorageInterface;
use App\Services\CvBuilderService;
use App\Services\CvValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

final class CvBuilder extends BaseController
{
    private CvBuilderService $builder;
    private PhotoStorageInterface $photoStorage;

    public function __construct()
    {
        $this->builder      = new CvBuilderService();
        $this->photoStorage = new LocalPhotoStorage();
    }

    public function edit(string $cvId, string $step = '1'): string
    {
        $stepNumber = filter_var($step, FILTER_VALIDATE_INT);
        if ($stepNumber === false || $stepNumber < 1 || $stepNumber > 6) {
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
            'sectionLabels' => $sectionLabels,
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

        try {
            $this->builder->saveStep($this->currentUser()->id, $cvId, $stepNumber, $payload);

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
        $this->builder->findOwned($this->currentUser()->id, $cvId)
            ?? throw PageNotFoundException::forPageNotFound();

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
                $oldPath = $this->builder->updatePhotoPath($this->currentUser()->id, $cvId, $newPath);
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
