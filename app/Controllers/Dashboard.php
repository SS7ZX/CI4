<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\CvAi\CvAiGenerationException;
use App\Models\Cv;
use App\Services\CvAiDraftService;
use App\Services\CvService;
use App\Services\CvPaymentService;
use App\Services\CvValidationException;
use App\Services\ThemeRegistry;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use Config\CvAi;

final class Dashboard extends BaseController
{
    public function index(): string
    {
        $user = $this->currentUser();
        $cvAi = config(CvAi::class);
        $cvAiStatus = ['available' => false, 'model_installed' => false, 'reason' => 'disabled'];
        if ($cvAi->enabled) {
            $cvAiStatus = service('cvAiDraftGenerator')->status();
        }
        $cvs  = (new Cv())
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'DESC')
            ->findAll();

        return view('dashboard/index', [
            'title'  => lang('Dashboard.title'),
            'cvs'    => $cvs,
            'themes' => array_column((new ThemeRegistry())->all(), null, 'key'),
            'userEmail' => (string) $user->email,
            'canReviewPayments' => (new CvPaymentService())->isAdmin((string) $user->email),
            'cvAiEnabled' => $cvAi->enabled,
            'cvAiStatus' => $cvAiStatus,
        ]);
    }

    public function quickCreate(): RedirectResponse
    {
        $post = $this->request->getPost();
        foreach (['full_name', 'headline', 'email', 'phone', 'city', 'summary', 'experience', 'education', 'skills'] as $field) {
            if (isset($post[$field]) && is_string($post[$field])) {
                $post[$field] = trim($post[$field]);
            }
        }

        if (! $this->validateData($post, [
            'full_name'  => 'required|max_length[160]',
            'headline'   => 'required|max_length[160]',
            'email'      => 'permit_empty|valid_email|max_length[254]',
            'phone'      => 'permit_empty|max_length[40]',
            'city'       => 'permit_empty|max_length[120]',
            'summary'    => 'permit_empty|max_length[3000]',
            'experience' => 'permit_empty|max_length[3000]',
            'education'  => 'permit_empty|max_length[3000]',
            'skills'     => 'permit_empty|max_length[3000]',
        ], [
            'full_name' => [
                'required'   => lang('Dashboard.quick_name_required'),
                'max_length' => lang('Dashboard.quick_name_too_long'),
            ],
            'headline' => [
                'required'   => lang('Dashboard.quick_headline_required'),
                'max_length' => lang('Dashboard.quick_headline_too_long'),
            ],
            'email' => [
                'valid_email' => lang('Dashboard.quick_email_invalid'),
                'max_length'  => lang('Dashboard.quick_email_too_long'),
            ],
            'phone' => ['max_length' => lang('Dashboard.quick_phone_too_long')],
            'city' => ['max_length' => lang('Dashboard.quick_city_too_long')],
            'summary' => ['max_length' => lang('Dashboard.quick_summary_too_long')],
            'experience' => ['max_length' => lang('Dashboard.quick_text_too_long')],
            'education' => ['max_length' => lang('Dashboard.quick_text_too_long')],
            'skills' => ['max_length' => lang('Dashboard.quick_text_too_long')],
        ])) {
            return redirect()->route('dashboard')->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $cvId = (new CvService())->createQuick($this->currentUser()->id, $post);
        } catch (CvValidationException $exception) {
            return redirect()->route('dashboard')->withInput()->with('errors', $exception->errors());
        }

        return redirect()
            ->to(url_to('cv-step', $cvId, 8))
            ->with('success', lang('Dashboard.quick_created'));
    }

    public function aiPrepare(): string|RedirectResponse
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $aiService = $this->cvAiDraftService();
        if (! $aiService->isEnabled()) {
            return redirect()->route('dashboard')->with('errors', [
                'ai' => lang('Dashboard.ai_disabled'),
            ]);
        }

        $input = $this->cvAiInput($this->request->getPost(), 'story');
        if (! $this->validateData($input, [
            'full_name' => 'required|max_length[160]',
            'email'     => 'permit_empty|valid_email|max_length[254]',
            'phone'     => 'permit_empty|max_length[40]',
            'city'      => 'permit_empty|max_length[120]',
            'story'     => 'required|max_length[8000]',
        ], $this->cvAiValidationMessages())) {
            return redirect()->route('dashboard')->with('errors', $this->validator->getErrors());
        }

        $identity = $this->cvAiIdentity($input);
        $story = $aiService->sanitizeStory($input['story'], $identity);

        return view('dashboard/ai-confirm', [
            'title'    => lang('Dashboard.ai_confirm_title'),
            'identity' => $identity,
            'story'    => $story,
            'errors'   => [],
        ]);
    }

    public function aiGenerate(): string|RedirectResponse
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $aiService = $this->cvAiDraftService();
        if (! $aiService->isEnabled()) {
            return redirect()->route('dashboard')->with('errors', [
                'ai' => lang('Dashboard.ai_disabled'),
            ]);
        }

        $input = $this->cvAiInput($this->request->getPost(), 'sanitized_story');
        $input['ai_consent'] = is_string($this->request->getPost('ai_consent'))
            ? trim((string) $this->request->getPost('ai_consent'))
            : '';
        $validationRules = [
            'full_name'      => 'required|max_length[160]',
            'email'          => 'permit_empty|valid_email|max_length[254]',
            'phone'          => 'permit_empty|max_length[40]',
            'city'           => 'permit_empty|max_length[120]',
            'sanitized_story' => 'required|max_length[8000]',
            'ai_consent'     => 'required|in_list[1]',
        ];
        if (! $this->validateData($input, $validationRules, $this->cvAiValidationMessages())) {
            return view('dashboard/ai-confirm', [
                'title'    => lang('Dashboard.ai_confirm_title'),
                'identity' => $this->cvAiIdentity($input),
                'story'    => $input['sanitized_story'],
                'errors'   => $this->validator->getErrors(),
            ]);
        }

        $identity = $this->cvAiIdentity($input);
        $story = $aiService->sanitizeStory($input['sanitized_story'], $identity);
        if ($story !== trim($input['sanitized_story'])) {
            return view('dashboard/ai-confirm', [
                'title'    => lang('Dashboard.ai_confirm_title'),
                'identity' => $identity,
                'story'    => $story,
                'errors'   => ['ai_consent' => lang('Dashboard.ai_review_redacted')],
            ]);
        }

        try {
            $generated = $aiService->generate($story);
        } catch (CvAiGenerationException $exception) {
            $reason = in_array($exception->reason, [
                'disabled',
                'input_too_long',
                'unavailable',
                'model_missing',
                'service_error',
                'output_too_large',
                'invalid_response',
            ], true) ? $exception->reason : 'service_error';

            return view('dashboard/ai-confirm', [
                'title'    => lang('Dashboard.ai_confirm_title'),
                'identity' => $identity,
                'story'    => $story,
                'errors'   => ['ai' => lang('Dashboard.ai_error_' . $reason)],
            ]);
        }

        return view('dashboard/ai-review', [
            'title'  => lang('Dashboard.ai_review_title'),
            'draft'  => [...$identity, ...$generated->toArray()],
            'errors' => [],
        ]);
    }

    public function aiSave(): string|RedirectResponse
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $post = $this->request->getPost();
        $draft = $this->cvAiReviewInput($post);
        if (! $this->validateData($draft, [
            'full_name'  => 'required|max_length[160]',
            'headline'   => 'required|max_length[160]',
            'email'      => 'permit_empty|valid_email|max_length[254]',
            'phone'      => 'permit_empty|max_length[40]',
            'city'       => 'permit_empty|max_length[120]',
            'summary'    => 'permit_empty|max_length[3000]',
            'experience' => 'permit_empty|max_length[3000]',
            'education'  => 'permit_empty|max_length[3000]',
            'skills'     => 'permit_empty|max_length[3000]',
        ], $this->cvAiValidationMessages())) {
            return view('dashboard/ai-review', [
                'title'  => lang('Dashboard.ai_review_title'),
                'draft'  => $draft,
                'errors' => $this->validator->getErrors(),
            ]);
        }

        try {
            $cvId = (new CvService())->createQuick($this->currentUser()->id, $draft);
        } catch (CvValidationException $exception) {
            return view('dashboard/ai-review', [
                'title'  => lang('Dashboard.ai_review_title'),
                'draft'  => $draft,
                'errors' => $exception->errors(),
            ]);
        }

        return redirect()
            ->to(url_to('cv-step', $cvId, 8))
            ->with('success', lang('Dashboard.ai_created'));
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

    private function cvAiDraftService(): CvAiDraftService
    {
        return new CvAiDraftService(
            config(CvAi::class),
            service('cvAiDraftGenerator'),
        );
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return array<string, string>
     */
    private function cvAiInput(array $post, string $storyField): array
    {
        $input = [];
        foreach (['full_name', 'email', 'phone', 'city', $storyField] as $field) {
            $value = $post[$field] ?? '';
            $input[$field] = is_string($value) ? trim($value) : '';
        }

        return $input;
    }

    /**
     * @param array<string, string> $input
     *
     * @return array<string, string>
     */
    private function cvAiIdentity(array $input): array
    {
        return [
            'full_name' => $input['full_name'] ?? '',
            'email'     => $input['email'] ?? '',
            'phone'     => $input['phone'] ?? '',
            'city'      => $input['city'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return array<string, string>
     */
    private function cvAiReviewInput(array $post): array
    {
        $draft = [];
        foreach (['full_name', 'headline', 'email', 'phone', 'city', 'summary', 'experience', 'education', 'skills'] as $field) {
            $value = $post[$field] ?? '';
            $draft[$field] = is_string($value) ? trim($value) : '';
        }

        return $draft;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function cvAiValidationMessages(): array
    {
        return [
            'full_name' => [
                'required'   => lang('Dashboard.quick_name_required'),
                'max_length' => lang('Dashboard.quick_name_too_long'),
            ],
            'headline' => [
                'required'   => lang('Dashboard.quick_headline_required'),
                'max_length' => lang('Dashboard.quick_headline_too_long'),
            ],
            'email' => [
                'valid_email' => lang('Dashboard.quick_email_invalid'),
                'max_length'  => lang('Dashboard.quick_email_too_long'),
            ],
            'phone' => ['max_length' => lang('Dashboard.quick_phone_too_long')],
            'city' => ['max_length' => lang('Dashboard.quick_city_too_long')],
            'story' => [
                'required'   => lang('Dashboard.ai_story_required'),
                'max_length' => lang('Dashboard.ai_story_too_long'),
            ],
            'sanitized_story' => [
                'required'   => lang('Dashboard.ai_story_required'),
                'max_length' => lang('Dashboard.ai_story_too_long'),
            ],
            'ai_consent' => [
                'required' => lang('Dashboard.ai_consent_required'),
                'in_list'  => lang('Dashboard.ai_consent_required'),
            ],
            'summary' => ['max_length' => lang('Dashboard.quick_summary_too_long')],
            'experience' => ['max_length' => lang('Dashboard.quick_text_too_long')],
            'education' => ['max_length' => lang('Dashboard.quick_text_too_long')],
            'skills' => ['max_length' => lang('Dashboard.quick_text_too_long')],
        ];
    }
}
