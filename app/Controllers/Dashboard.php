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
use CodeIgniter\HTTP\ResponseInterface;
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

    public function aiStart(): string
    {
        $profile = [
            'full_name'  => '',
            'target_role' => '',
            'email'      => '',
            'phone'      => '',
            'city'       => '',
        ];

        return $this->aiStartView($profile);
    }

    public function aiStory(): string
    {
        $profile = $this->cvAiProfileInput($this->request->getPost());
        if (! $this->validateData($profile, [
            'full_name'   => 'required|max_length[160]',
            'target_role' => 'required|max_length[160]',
            'email'       => 'permit_empty|valid_email|max_length[254]',
            'phone'       => 'permit_empty|max_length[40]',
            'city'        => 'permit_empty|max_length[120]',
        ], $this->cvAiValidationMessages())) {
            return $this->aiStartView($profile, $this->validator->getErrors());
        }

        return $this->aiStoryView($profile);
    }

    public function aiConsent(): string
    {
        $profile = $this->cvAiProfileInput($this->request->getPost());
        $story = $this->postText($this->request->getPost(), 'story');
        if (! $this->validateData([...$profile, 'story' => $story], [
            'full_name'   => 'required|max_length[160]',
            'target_role' => 'required|max_length[160]',
            'email'       => 'permit_empty|valid_email|max_length[254]',
            'phone'       => 'permit_empty|max_length[40]',
            'city'        => 'permit_empty|max_length[120]',
            'story'       => 'required|max_length[8000]',
        ], $this->cvAiValidationMessages())) {
            return $this->aiStoryView($profile, $story, $this->validator->getErrors());
        }

        $aiService = $this->cvAiDraftService();
        $identity = $this->cvAiIdentity($profile);
        $sanitizedStory = $aiService->sanitizeStory($story, $identity);
        $targetRole = $aiService->sanitizeStory($profile['target_role'], $identity);

        return $this->aiConsentView(
            [...$identity, 'target_role' => $targetRole],
            $sanitizedStory,
            [],
        );
    }

    public function aiLive(): string
    {
        $input = $this->cvAiGenerationInput($this->request->getPost());
        if (! $this->validateData($input, $this->cvAiGenerationRules(), $this->cvAiValidationMessages())) {
            return $this->aiConsentView(
                $this->cvAiIdentity($input) + ['target_role' => $input['target_role']],
                $input['sanitized_story'],
                $this->validator->getErrors(),
            );
        }

        $aiService = $this->cvAiDraftService();
        if (! $aiService->isEnabled()) {
            return $this->aiConsentView(
                $this->cvAiIdentity($input) + ['target_role' => $input['target_role']],
                $input['sanitized_story'],
                ['ai' => lang('Dashboard.ai_disabled')],
            );
        }
        $identity = $this->cvAiIdentity($input);
        $sanitizedStory = $aiService->sanitizeStory($input['sanitized_story'], $identity);
        $targetRole = $aiService->sanitizeStory($input['target_role'], $identity);
        if (
            $sanitizedStory !== $input['sanitized_story']
            || $targetRole !== $input['target_role']
        ) {
            return $this->aiConsentView(
                $identity + ['target_role' => $targetRole],
                $sanitizedStory,
                ['ai' => lang('Dashboard.ai_review_redacted')],
            );
        }

        return $this->aiLiveView([
            ...$input,
            'sanitized_story' => $sanitizedStory,
            'target_role' => $targetRole,
        ]);
    }

    public function aiReview(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $post = $this->request->getPost();
        $draft = $this->cvAiReviewInput($post);
        $sanitizedStory = $this->postText($post, 'sanitized_story');
        if (! $this->validateData([...$draft, 'sanitized_story' => $sanitizedStory], [
            'full_name'       => 'required|max_length[160]',
            'headline'        => 'required|max_length[160]',
            'email'           => 'permit_empty|valid_email|max_length[254]',
            'phone'           => 'permit_empty|max_length[40]',
            'city'            => 'permit_empty|max_length[120]',
            'summary'         => 'permit_empty|max_length[3000]',
            'experience'      => 'permit_empty|max_length[3000]',
            'education'        => 'permit_empty|max_length[3000]',
            'skills'           => 'permit_empty|max_length[3000]',
            'sanitized_story'  => 'required|max_length[8000]',
        ], $this->cvAiValidationMessages())) {
            return $this->aiReviewView(
                $draft,
                $sanitizedStory,
                lang('Dashboard.ai_refine_default_instruction'),
                '',
                $this->validator->getErrors(),
            );
        }

        $identity = $this->cvAiIdentity($draft);
        $aiService = $this->cvAiDraftService();
        $wasRedacted = $aiService->sanitizeStory($sanitizedStory, $identity) !== $sanitizedStory;
        $sanitizedStory = $aiService->sanitizeStory($sanitizedStory, $identity);
        foreach (['headline', 'summary', 'experience', 'education', 'skills'] as $field) {
            $sanitized = $aiService->sanitizeStory($draft[$field], $identity);
            $wasRedacted = $wasRedacted || $sanitized !== $draft[$field];
            $draft[$field] = $sanitized;
        }

        return $this->aiReviewView(
            $draft,
            $sanitizedStory,
            lang('Dashboard.ai_refine_default_instruction'),
            '',
            $wasRedacted ? ['ai' => lang('Dashboard.ai_review_redacted')] : [],
        );
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
        $targetRole = $aiService->sanitizeStory($this->postText($this->request->getPost(), 'target_role'), $identity);

        return view('dashboard/ai-confirm', [
            'title'    => lang('Dashboard.ai_confirm_title'),
            'identity' => $identity,
            'story'    => $story,
            'targetRole' => $targetRole,
            'errors'   => [],
        ]);
    }

    public function aiGenerate(): string|RedirectResponse|ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $streaming = str_contains($this->request->getHeaderLine('Accept'), 'text/event-stream');
        $aiService = $this->cvAiDraftService();
        if (! $aiService->isEnabled()) {
            if ($streaming) {
                return $this->sendAiStreamError(lang('Dashboard.ai_error_disabled'));
            }

            return redirect()->route('dashboard')->with('errors', [
                'ai' => lang('Dashboard.ai_disabled'),
            ]);
        }

        $input = $this->cvAiGenerationInput($this->request->getPost());
        if (! $this->validateData($input, $this->cvAiGenerationRules(), $this->cvAiValidationMessages())) {
            $message = implode(' ', $this->validator->getErrors());

            return $streaming
                ? $this->sendAiStreamError($message)
                : $this->aiConsentView(
                    $this->cvAiIdentity($input) + ['target_role' => $input['target_role']],
                    $input['sanitized_story'],
                    $this->validator->getErrors(),
                );
        }

        $identity = $this->cvAiIdentity($input);
        $story = $aiService->sanitizeStory($input['sanitized_story'], $identity);
        $targetRole = $aiService->sanitizeStory($input['target_role'], $identity);
        if ($story !== $input['sanitized_story'] || $targetRole !== $input['target_role']) {
            $error = lang('Dashboard.ai_review_redacted');

            return $streaming
                ? $this->sendAiStreamError($error)
                : $this->aiConsentView(
                    $identity + ['target_role' => $targetRole],
                    $story,
                    ['ai' => $error],
                );
        }

        if ($streaming) {
            return $this->streamAiDraft($aiService, $story, $targetRole);
        }

        try {
            $generated = $aiService->generate($story);
        } catch (CvAiGenerationException $exception) {
            return view('dashboard/ai-confirm', [
                'title'    => lang('Dashboard.ai_confirm_title'),
                'identity' => $identity,
                'story'    => $story,
                'targetRole' => $targetRole,
                'errors'   => ['ai' => $this->cvAiErrorMessage($exception)],
            ]);
        }

        return $this->aiReviewView(
            [...$identity, ...$generated->toArray()],
            $story,
            lang('Dashboard.ai_refine_default_instruction'),
        );
    }

    public function aiRefineSummary(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $draft = $this->cvAiReviewInput($this->request->getPost());
        $sanitizedStory = $this->postText($this->request->getPost(), 'sanitized_story');
        $instruction = $this->postText($this->request->getPost(), 'summary_instruction');
        $additionalFacts = $this->postText($this->request->getPost(), 'additional_facts');
        $input = [
            ...$draft,
            'sanitized_story' => $sanitizedStory,
            'summary_instruction' => $instruction,
            'additional_facts' => $additionalFacts,
            'ai_consent' => $this->postText($this->request->getPost(), 'ai_consent'),
        ];

        if (! $this->validateData($input, [
            'full_name'         => 'required|max_length[160]',
            'headline'          => 'required|max_length[160]',
            'email'             => 'permit_empty|valid_email|max_length[254]',
            'phone'             => 'permit_empty|max_length[40]',
            'city'              => 'permit_empty|max_length[120]',
            'summary'           => 'permit_empty|max_length[3000]',
            'experience'        => 'permit_empty|max_length[3000]',
            'education'         => 'permit_empty|max_length[3000]',
            'skills'            => 'permit_empty|max_length[3000]',
            'sanitized_story'   => 'required|max_length[8000]',
            'summary_instruction' => 'required|max_length[500]',
            'additional_facts'  => 'permit_empty|max_length[2000]',
            'ai_consent'        => 'required|in_list[1]',
        ], $this->cvAiValidationMessages())) {
            return $this->aiReviewView(
                $draft,
                $sanitizedStory,
                $instruction,
                $additionalFacts,
                $this->validator->getErrors(),
            );
        }

        $aiService = $this->cvAiDraftService();
        $identity = $this->cvAiIdentity($draft);
        $sanitizedStory = $aiService->sanitizeStory($sanitizedStory, $identity);
        $instruction = $aiService->sanitizeStory($instruction, $identity);
        $additionalFacts = $aiService->sanitizeStory($additionalFacts, $identity);
        $wasRedacted = $sanitizedStory !== $input['sanitized_story']
            || $instruction !== $input['summary_instruction']
            || $additionalFacts !== $input['additional_facts'];

        foreach (['summary', 'headline', 'experience', 'education', 'skills'] as $field) {
            $draft[$field] = $aiService->sanitizeStory($draft[$field], $identity);
            $wasRedacted = $wasRedacted || $draft[$field] !== $input[$field];
        }

        if ($wasRedacted) {
            return $this->aiReviewView(
                $draft,
                $sanitizedStory,
                $instruction,
                $additionalFacts,
                ['ai' => lang('Dashboard.ai_review_redacted')],
            );
        }

        try {
            $draft['summary'] = $aiService->refineSummary(
                $sanitizedStory,
                [
                    'summary' => $draft['summary'],
                    'headline' => $draft['headline'],
                    'experience' => $draft['experience'],
                    'education' => $draft['education'],
                    'skills' => $draft['skills'],
                    'additional_facts' => $additionalFacts,
                ],
                $instruction,
            );
        } catch (CvAiGenerationException $exception) {
            return $this->aiReviewView(
                $draft,
                $sanitizedStory,
                $instruction,
                $additionalFacts,
                ['ai' => $this->cvAiErrorMessage($exception)],
            );
        }

        return $this->aiReviewView(
            $draft,
            $sanitizedStory,
            $instruction,
            $additionalFacts,
            [],
            $draft['summary'] !== '' ? lang('Dashboard.ai_summary_refined') : '',
        );
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
            return $this->aiReviewView(
                $draft,
                $this->postText($post, 'sanitized_story'),
                $this->postText($post, 'summary_instruction'),
                $this->postText($post, 'additional_facts'),
                $this->validator->getErrors(),
            );
        }

        try {
            $cvId = (new CvService())->createQuick($this->currentUser()->id, $draft);
        } catch (CvValidationException $exception) {
            return $this->aiReviewView(
                $draft,
                $this->postText($post, 'sanitized_story'),
                $this->postText($post, 'summary_instruction'),
                $this->postText($post, 'additional_facts'),
                $exception->errors(),
            );
        }

        return redirect()
            ->to(url_to('cv-step', $cvId, 8))
            ->with('success', lang('Dashboard.ai_created'));
    }

    /**
     * @param array<string, string> $profile
     * @param array<string, string> $errors
     */
    private function aiStartView(array $profile, array $errors = []): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/ai-start', [
            'title' => lang('Dashboard.ai_start_title'),
            'profile' => $profile,
            'errors' => $errors,
            'enabled' => $this->cvAiDraftService()->isEnabled(),
        ]);
    }

    /**
     * @param array<string, string> $profile
     * @param array<string, string> $errors
     */
    private function aiStoryView(array $profile, string $story = '', array $errors = []): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/ai-story', [
            'title' => lang('Dashboard.ai_story_page_title'),
            'profile' => $profile,
            'story' => $story,
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<string, string> $profile
     * @param array<string, string> $errors
     */
    private function aiConsentView(array $profile, string $story, array $errors): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/ai-confirm', [
            'title' => lang('Dashboard.ai_confirm_title'),
            'identity' => $this->cvAiIdentity($profile),
            'targetRole' => $profile['target_role'] ?? '',
            'story' => $story,
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<string, string> $input
     */
    private function aiLiveView(array $input): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/ai-live', [
            'title' => lang('Dashboard.ai_live_title'),
            'input' => $input,
        ]);
    }

    private function streamAiDraft(CvAiDraftService $aiService, string $story, string $targetRole): ResponseInterface
    {
        $this->beginAiEventStream();
        try {
            $draft = $aiService->generateStream(
                $story,
                $targetRole,
                function (string $text): void {
                    if (! $this->sendAiEvent('partial', ['text' => $text])) {
                        throw new CvAiGenerationException('cancelled');
                    }
                },
            );
            $this->sendAiEvent('complete', ['draft' => $draft->toArray()]);
        } catch (CvAiGenerationException $exception) {
            if ($exception->reason !== 'cancelled') {
                $this->sendAiEvent('error', ['message' => $this->cvAiErrorMessage($exception)]);
            }
        } catch (\Throwable) {
            $this->sendAiEvent('error', ['message' => lang('Dashboard.ai_error_service_error')]);
        }

        return $this->response->setBody('');
    }

    private function sendAiStreamError(string $message): ResponseInterface
    {
        $this->beginAiEventStream();
        $this->sendAiEvent('error', ['message' => $message]);

        return $this->response->setBody('');
    }

    private function beginAiEventStream(): void
    {
        service('cvAiEventStream')->begin($this->response);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendAiEvent(string $event, array $payload): bool
    {
        return service('cvAiEventStream')->send($event, $payload);
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return array<string, string>
     */
    private function cvAiProfileInput(array $post): array
    {
        $profile = [];
        foreach (['full_name', 'target_role', 'email', 'phone', 'city'] as $field) {
            $profile[$field] = $this->postText($post, $field);
        }

        return $profile;
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return array<string, string>
     */
    private function cvAiGenerationInput(array $post): array
    {
        $input = $this->cvAiProfileInput($post);
        $input['sanitized_story'] = $this->postText($post, 'sanitized_story');
        $input['ai_consent'] = $this->postText($post, 'ai_consent');

        return $input;
    }

    /**
     * @return array<string, string>
     */
    private function cvAiGenerationRules(): array
    {
        return [
            'full_name'       => 'required|max_length[160]',
            'target_role'     => 'permit_empty|max_length[160]',
            'email'           => 'permit_empty|valid_email|max_length[254]',
            'phone'           => 'permit_empty|max_length[40]',
            'city'            => 'permit_empty|max_length[120]',
            'sanitized_story' => 'required|max_length[8000]',
            'ai_consent'      => 'required|in_list[1]',
        ];
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

    private function cvAiErrorMessage(CvAiGenerationException $exception): string
    {
        $reason = in_array($exception->reason, [
            'disabled',
            'input_too_long',
            'unavailable',
            'model_missing',
            'service_error',
            'output_too_large',
            'invalid_response',
            'timeout',
            'cancelled',
        ], true) ? $exception->reason : 'service_error';

        return lang('Dashboard.ai_error_' . $reason);
    }

    /**
     * @param array<string, string> $draft
     * @param array<string, string> $errors
     */
    private function aiReviewView(
        array $draft,
        string $sanitizedStory,
        string $summaryInstruction,
        string $additionalFacts = '',
        array $errors = [],
        string $message = '',
    ): string {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/ai-review', [
            'title' => lang('Dashboard.ai_review_title'),
            'draft' => $draft,
            'sanitizedStory' => $sanitizedStory,
            'summaryInstruction' => $summaryInstruction,
            'additionalFacts' => $additionalFacts,
            'errors' => $errors,
            'message' => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function postText(array $post, string $field): string
    {
        $value = $post[$field] ?? '';

        return is_string($value) ? trim($value) : '';
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
            'target_role' => [
                'required'   => lang('Dashboard.ai_target_required'),
                'max_length' => lang('Dashboard.ai_target_too_long'),
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
            'summary_instruction' => [
                'required'   => lang('Dashboard.ai_refine_instruction_required'),
                'max_length' => lang('Dashboard.ai_refine_instruction_too_long'),
            ],
            'additional_facts' => [
                'max_length' => lang('Dashboard.ai_refine_facts_too_long'),
            ],
            'summary' => ['max_length' => lang('Dashboard.quick_summary_too_long')],
            'experience' => ['max_length' => lang('Dashboard.quick_text_too_long')],
            'education' => ['max_length' => lang('Dashboard.quick_text_too_long')],
            'skills' => ['max_length' => lang('Dashboard.quick_text_too_long')],
        ];
    }
}
