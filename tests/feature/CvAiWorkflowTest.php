<?php

declare(strict_types=1);

use App\Libraries\CvAi\CvAiDraft;
use App\Libraries\CvAi\CvAiDraftGeneratorInterface;
use App\Models\Cv;
use CodeIgniter\Shield\Models\UserModel;
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
                'summary'    => 'Mengelola proyek dan menyusun laporan berkala. Mengatur jadwal kerja, memantau progres, serta mendokumentasikan hasil.',
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
