<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cv;
use App\Services\CvService;
use App\Services\ThemeRegistry;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;

final class Dashboard extends BaseController
{
    public function index(): string
    {
        $user = $this->currentUser();
        $cvs  = (new Cv())
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'DESC')
            ->findAll();

        return view('dashboard/index', [
            'title'  => lang('Dashboard.title'),
            'cvs'    => $cvs,
            'themes' => array_column((new ThemeRegistry())->all(), null, 'key'),
        ]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), [
            'title' => 'required|max_length[160]',
        ], [
            'title' => [
                'required'   => lang('Dashboard.title_required'),
                'max_length' => lang('Dashboard.title_too_long'),
            ],
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $title = trim((string) $this->request->getPost('title'));
        if ($title === '') {
            return redirect()->back()->withInput()->with('errors', [
                'title' => lang('Dashboard.title_required'),
            ]);
        }

        $cvId = (new CvService())->create($this->currentUser()->id, $title);

        return redirect()->route('cv-show', [$cvId])->with('success', lang('Dashboard.created'));
    }

    public function show(string $cvId): string
    {
        $cv = (new CvService())->findOwned($this->currentUser()->id, $cvId);
        if ($cv === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('dashboard/cv', [
            'title' => $cv->title,
            'cv'    => $cv,
        ]);
    }

    public function duplicate(string $cvId): RedirectResponse
    {
        (new CvService())->duplicate($this->currentUser()->id, $cvId);

        return redirect()->route('dashboard')->with('success', lang('Dashboard.duplicated'));
    }

    public function delete(string $cvId): RedirectResponse
    {
        (new CvService())->softDelete($this->currentUser()->id, $cvId);

        return redirect()->route('dashboard')->with('success', lang('Dashboard.deleted'));
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
