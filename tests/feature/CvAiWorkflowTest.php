<?php

declare(strict_types=1);

use App\Libraries\CvAi\CvAiDraft;
use App\Libraries\CvAi\CvAiDraftGeneratorInterface;
use App\Libraries\CvAi\CvAiEventStreamInterface;
use App\Models\Cv;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\CvAi;
use Config\Services;

/**
 * @internal
 */
final class CvAiWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    protected function tearDown(): void
    {
        Services::resetSingle('cvAiDraftGenerator');
        Services::resetSingle('cvAiEventStream');
        config(CvAi::class)->enabled = false;
        auth()->logout();

        parent::tearDown();
    }

    public function testDashboardKeepsAiDisabledAndQuickCreateAvailable(): void
    {
        $userId = $this->createUser('ai-disabled@example.test', 'aidisabled');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = false;

        try {
            $response = $this->get('/dashboard');

            $response->assertOK();
            $response->assertSee(lang('Dashboard.ai_disabled'));
            $response->assertSee(lang('Dashboard.quick_title'));
            $this->assertStringNotContainsString('href="' . url_to('cv-ai-start') . '"', $response->getBody());
        } finally {
            auth()->logout();
        }
    }

    public function testDashboardLinksToTheGuidedAiFlowWhenLocalModelIsReady(): void
    {
        $userId = $this->createUser('ai-ready@example.test', 'aiready');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = true;

        $generator = $this->createMock(CvAiDraftGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('status')
            ->willReturn([
                'available' => true,
                'model_installed' => true,
                'reason' => 'ready',
            ]);
        Services::injectMock('cvAiDraftGenerator', $generator);

        try {
            $response = $this->get('/dashboard');

            $response->assertOK();
            $response->assertSee(lang('Dashboard.ai_local_ready'));
            $response->assertSee(lang('Dashboard.ai_start_action'));
            $this->assertStringContainsString('href="' . url_to('cv-ai-start') . '"', $response->getBody());
        } finally {
            auth()->logout();
        }
    }

    public function testFiveStepPagesRedactBeforeConsentAndKeepReviewTransient(): void
    {
        $userId = $this->createUser('ai-five-steps@example.test', 'aifivesteps');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = true;

        try {
            $this->assertSame('/dashboard/cv/ai', parse_url(url_to('cv-ai-start'), PHP_URL_PATH));
            $start = $this->get('/dashboard/cv/ai');
            $start->assertOK();
            $start->assertSee(lang('Dashboard.ai_steps')[0]);
            $this->assertStringContainsString('private', $start->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString('no-store', $start->response()->getHeaderLine('Cache-Control'));

            $profile = [
                'full_name' => 'Dina Pratama',
                'target_role' => 'Koordinator Proyek',
                'email' => 'dina@example.test',
                'phone' => '+62 812 3456 7890',
                'city' => 'Bandung',
            ];
            $story = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
                ->post('/dashboard/cv/ai/story', $profile);
            $story->assertOK();
            $story->assertSee(lang('Dashboard.ai_steps')[1]);

            $consent = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
                ->post('/dashboard/cv/ai/consent', [
                    ...$profile,
                    'story' => 'Dina Pratama, dina@example.test, +62 812 3456 7890 mengelola proyek, menyusun laporan berkala, mengatur jadwal kerja, memantau progres, dan mendokumentasikan hasil.',
                ]);
            $consent->assertOK();
            $consent->assertSee(lang('Dashboard.ai_steps')[2]);
            $consent->assertSee('[disamarkan]');
            $storyMatch = preg_match(
                '/<textarea[^>]*name="sanitized_story"[^>]*>(.*?)<\/textarea>/s',
                $consent->getBody(),
                $storyMatches,
            );
            $this->assertSame(1, $storyMatch);
            $consentedStory = html_entity_decode($storyMatches[1], ENT_QUOTES | ENT_HTML5);
            $this->assertStringNotContainsString('dina@example.test', $consentedStory);
            $this->assertStringNotContainsString('+62 812 3456 7890', $consentedStory);

            $sanitizedStory = 'mengelola proyek, menyusun laporan berkala, mengatur jadwal kerja, memantau progres, dan mendokumentasikan hasil.';
            $live = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
                ->post('/dashboard/cv/ai/live', [
                    ...$profile,
                    'sanitized_story' => $sanitizedStory,
                    'ai_consent' => '1',
                ]);
            $live->assertOK();
            $live->assertSee(lang('Dashboard.ai_steps')[3]);
            $live->assertSee('/assets/js/cv-ai-stream.js');

            $review = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
                ->post('/dashboard/cv/ai/review', [
                    ...$profile,
                    'sanitized_story' => $sanitizedStory,
                    'headline' => 'Koordinator Proyek',
                    'summary' => '',
                    'experience' => 'Mengelola proyek dan laporan.',
                    'education' => '',
                    'skills' => '',
                ]);
            $review->assertOK();
            $review->assertSee(lang('Dashboard.ai_steps')[4]);
            $this->assertSame(0, (new Cv())->where('user_id', $userId)->countAllResults());

            $saved = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
                ->post('/dashboard/cv/ai/save', [
                    ...$profile,
                    'headline' => 'Koordinator Proyek',
                    'summary' => '',
                    'experience' => 'Mengelola proyek dan laporan.',
                    'education' => '',
                    'skills' => '',
                ]);
            $saved->assertRedirect();
            $this->assertSame(1, (new Cv())->where('user_id', $userId)->countAllResults());
        } finally {
            auth()->logout();
        }
    }

    public function testStreamingGenerationRequiresConsentAndReturnsOnlyTransientValidatedDraft(): void
    {
        $userId = $this->createUser('ai-stream@example.test', 'aistream');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = true;

        $generator = $this->createMock(CvAiDraftGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('generateStream')
            ->with(
                'Mengelola proyek dan menyusun laporan berkala.',
                'Koordinator Proyek',
                $this->callback(static fn (callable $onPartial): bool => is_callable($onPartial)),
            )
            ->willReturnCallback(static function (
                string $story,
                string $targetRole,
                callable $onPartial,
            ): CvAiDraft {
                $onPartial('{"headline":"Koordinator ');

                return new CvAiDraft('Koordinator Proyek', '', 'Mengelola proyek.', '', '');
            });
        Services::injectMock('cvAiDraftGenerator', $generator);
        $eventStream = $this->createMock(CvAiEventStreamInterface::class);
        $eventStream->expects($this->once())
            ->method('begin')
            ->with($this->isInstanceOf(ResponseInterface::class))
            ->willReturnCallback(static function (ResponseInterface $response): void {
                $response->setStatusCode(200)
                    ->setHeader('Content-Type', 'text/event-stream; charset=utf-8')
                    ->setHeader('Cache-Control', 'private, no-store');
            });
        $eventStream->expects($this->exactly(2))->method('send')->willReturn(true);
        Services::injectMock('cvAiEventStream', $eventStream);

        try {
            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept' => 'text/event-stream',
            ])->post('/dashboard/cv/ai/generate', [
                'full_name' => 'Rani Pratama',
                'target_role' => 'Koordinator Proyek',
                'email' => 'ai-stream@example.test',
                'phone' => '',
                'city' => '',
                'sanitized_story' => 'Mengelola proyek dan menyusun laporan berkala.',
                'ai_consent' => '1',
            ]);

            $this->assertSame(200, $response->response()->getStatusCode());
            $this->assertStringContainsString('text/event-stream', $response->response()->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
            $this->assertSame(0, (new Cv())->where('user_id', $userId)->countAllResults());
        } finally {
            auth()->logout();
        }
    }

    public function testGenerationKeepsDraftTransientUntilUserSaves(): void
    {
        $userId = $this->createUser('ai-cv@example.test', 'aicv');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = true;

        $generator = $this->createMock(CvAiDraftGeneratorInterface::class);
        Services::injectMock('cvAiDraftGenerator', $generator);

        try {
            $prepared = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/prepare', [
                'full_name' => 'Dina Pratama',
                'email'     => 'dina@example.test',
                'phone'     => '+62 812 3456 7890',
                'city'      => 'Bandung',
                'story'     => 'Dina Pratama, dina@example.test, +62 812 3456 7890 tinggal di Bandung. Mengelola proyek, menyusun laporan berkala, mengatur jadwal kerja, memantau progres, dan mendokumentasikan hasil.',
            ]);
            $prepared->assertOK();
            $this->assertStringContainsString('private', $prepared->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString('no-store', $prepared->response()->getHeaderLine('Cache-Control'));
            $prepared->assertSee('[disamarkan]');
            $prepared->assertSee(lang('Dashboard.ai_steps')[1]);
            $matchCount = preg_match('/<textarea[^>]*name="sanitized_story"[^>]*>(.*?)<\/textarea>/s', $prepared->getBody(), $matches);
            $this->assertSame(1, $matchCount);
            $preparedStory = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
            $this->assertStringNotContainsString('dina@example.test', $preparedStory);
            $this->assertStringNotContainsString('+62 812 3456 7890', $preparedStory);
            $generator->expects($this->once())
                ->method('generate')
                ->with($preparedStory)
                ->willReturn(new CvAiDraft(
                    'Koordinator Proyek',
                    '',
                    'Mengelola proyek dan laporan.',
                    'Administrasi Bisnis',
                    'Manajemen proyek, Pelaporan',
                ));
            $refinedSummary = 'Mengelola proyek, menyusun laporan berkala, dan mengatur jadwal kerja. Memantau progres, mendokumentasikan hasil, mengoordinasikan rapat, serta memperbarui laporan untuk setiap kegiatan proyek.';
            $generator->expects($this->once())
                ->method('refineSummary')
                ->with(
                    $preparedStory,
                    $this->callback(static fn (array $context): bool => $context['summary'] === ''
                        && $context['experience'] === 'Mengelola proyek dan laporan.'
                        && $context['additional_facts'] === 'Mengoordinasikan rapat dan memperbarui laporan proyek.'),
                    'Buat ringkasan lebih lengkap dan profesional.',
                )
                ->willReturn($refinedSummary);
            $this->assertSame(0, (new Cv())->where('user_id', $userId)->countAllResults());

            $generated = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/generate', [
                'full_name'       => 'Dina Pratama',
                'email'           => 'dina@example.test',
                'phone'           => '+62 812 3456 7890',
                'city'            => 'Bandung',
                'sanitized_story' => $preparedStory,
                'ai_consent'      => '1',
            ]);
            $generated->assertOK();
            $this->assertStringContainsString('private', $generated->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString('no-store', $generated->response()->getHeaderLine('Cache-Control'));
            $generated->assertSee('Koordinator Proyek');
            $generated->assertSee(lang('Dashboard.ai_summary_not_generated'));
            $generated->assertSee(lang('Dashboard.ai_summary_refine_title'));
            $generated->assertSee(lang('Dashboard.ai_refine_button'));
            $generated->assertSee(lang('Dashboard.ai_steps')[2]);
            $this->assertSame(0, (new Cv())->where('user_id', $userId)->countAllResults());

            $refined = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/summary/refine', [
                'full_name' => 'Dina Pratama',
                'headline' => 'Koordinator Proyek',
                'email' => 'dina@example.test',
                'phone' => '+62 812 3456 7890',
                'city' => 'Bandung',
                'summary' => '',
                'experience' => 'Mengelola proyek dan laporan.',
                'education' => 'Administrasi Bisnis',
                'skills' => 'Manajemen proyek, Pelaporan',
                'sanitized_story' => $preparedStory,
                'summary_instruction' => 'Buat ringkasan lebih lengkap dan profesional.',
                'additional_facts' => 'Mengoordinasikan rapat dan memperbarui laporan proyek.',
                'ai_consent' => '1',
            ]);
            $refined->assertOK();
            $refined->assertSee(lang('Dashboard.ai_summary_refined'));
            $refined->assertSee($refinedSummary);
            $this->assertSame(0, (new Cv())->where('user_id', $userId)->countAllResults());

            $saved = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/save', [
                'full_name'  => 'Dina Pratama',
                'headline'   => 'Koordinator Proyek',
                'email'      => 'dina@example.test',
                'phone'      => '+62 812 3456 7890',
                'city'       => 'Bandung',
                'summary'    => $refinedSummary,
                'experience' => 'Mengelola proyek dan laporan.',
                'education'  => 'Administrasi Bisnis',
                'skills'     => 'Manajemen proyek, Pelaporan',
            ]);
            $saved->assertRedirect();
            $this->assertSame(1, (new Cv())->where('user_id', $userId)->countAllResults());
        } finally {
            auth()->logout();
        }
    }

    public function testGenerationRequiresExplicitConsentAndDoesNotSaveInput(): void
    {
        $userId = $this->createUser('ai-consent@example.test', 'aiconsent');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = true;

        $generator = $this->createMock(CvAiDraftGeneratorInterface::class);
        $generator->expects($this->never())->method('generate');
        Services::injectMock('cvAiDraftGenerator', $generator);

        try {
            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/generate', [
                'full_name'       => 'Rani Contoh',
                'sanitized_story' => 'Menyusun laporan.',
            ]);

            $response->assertOK();
            $response->assertSee(lang('Dashboard.ai_consent_required'));
            $this->assertSame(0, (new Cv())->where('user_id', $userId)->countAllResults());
        } finally {
            auth()->logout();
        }
    }

    public function testSummaryRefinementRequiresConsentAndStopsWhenPersonalDataIsFound(): void
    {
        $userId = $this->createUser('ai-refine@example.test', 'airefine');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = true;

        $generator = $this->createMock(CvAiDraftGeneratorInterface::class);
        $generator->expects($this->never())->method('refineSummary');
        Services::injectMock('cvAiDraftGenerator', $generator);

        $input = [
            'full_name' => 'Rani Contoh',
            'headline' => 'Koordinator Proyek',
            'email' => 'ai-refine@example.test',
            'phone' => '',
            'city' => '',
            'summary' => '',
            'experience' => 'Mengelola proyek.',
            'education' => '',
            'skills' => '',
            'sanitized_story' => 'Mengelola proyek dan menyusun laporan berkala.',
            'summary_instruction' => 'Buat ringkasan lebih lengkap.',
            'additional_facts' => '',
        ];

        try {
            $withoutConsent = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/summary/refine', $input);
            $withoutConsent->assertOK();
            $withoutConsent->assertSee(lang('Dashboard.ai_consent_required'));

            $input['ai_consent'] = '1';
            $input['additional_facts'] = 'Email saya rani@example.test.';
            $personalData = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/summary/refine', $input);
            $personalData->assertOK();
            $personalData->assertSee(lang('Dashboard.ai_review_redacted'));
            $personalData->assertDontSee('rani@example.test');
        } finally {
            auth()->logout();
        }
    }

    public function testNewPersonalDataMustBeReviewedBeforeGeneration(): void
    {
        $userId = $this->createUser('ai-redact@example.test', 'aireda');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        config(CvAi::class)->enabled = true;

        $generator = $this->createMock(CvAiDraftGeneratorInterface::class);
        $generator->expects($this->never())->method('generate');
        Services::injectMock('cvAiDraftGenerator', $generator);

        try {
            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/ai/generate', [
                'full_name'       => 'Rani Contoh',
                'sanitized_story' => 'Email saya rani@example.test. Mengelola laporan.',
                'ai_consent'      => '1',
            ]);

            $response->assertOK();
            $response->assertSee(lang('Dashboard.ai_review_redacted'));
            $response->assertDontSee('rani@example.test');
            $this->assertSame(0, (new Cv())->where('user_id', $userId)->countAllResults());
        } finally {
            auth()->logout();
        }
    }

    private function createUser(string $email, string $username): int
    {
        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => $username,
            'email'    => $email,
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);

        return $users->getInsertID();
    }
}
