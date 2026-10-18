<?php

declare(strict_types=1);

use App\Libraries\Pdf\DompdfRenderer;
use App\Services\CvBuilderService;
use App\Services\CvRenderService;
use App\Services\CvService;
use App\Services\ThemeRegistry;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class CvPdfTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testStepEightSavesSettingsAndDownloadHasSafeHeaders(): void
    {
        $userId = $this->createUser('pdf-settings@example.test', 'pdf-settings');
        $cvId = (new CvService())->create($userId, 'CV / laporan kerja');
        $this->addProfileAndExperience($userId, $cvId, 'CV / laporan kerja');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $editor = $this->get('/dashboard/cv/' . $cvId . '/step/8');
        $editor->assertOK();
        $editor->assertSee('Tinjau dan unduh');
        $editor->assertSee('Ukuran kertas');

        $this->withSession($_SESSION);
        $invalid = $this->withBodyFormat('json')
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'Accept' => 'application/json'])
            ->post('/dashboard/cv/' . $cvId . '/autosave/8', [
                'paper_size' => 'a3',
                'font_scale' => 'huge',
                'accent_color' => '#FFFFFF',
            ]);
        $invalid->assertStatus(422);
        $errors = json_decode($invalid->getJSON(), true, 512, JSON_THROW_ON_ERROR)['errors'];
        $this->assertArrayHasKey('paper_size', $errors);
        $this->assertArrayHasKey('font_scale', $errors);
        $this->assertArrayHasKey('accent_color', $errors);

        $this->withSession($_SESSION);
        $saved = $this->withBodyFormat('json')
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'Accept' => 'application/json'])
            ->post('/dashboard/cv/' . $cvId . '/autosave/8', [
                'paper_size' => 'letter',
                'font_scale' => 'large',
                'accent_color' => '#1D4ED8',
            ]);
        $saved->assertOK();
        $cv = (new CvService())->findOwned($userId, $cvId);
        $this->assertSame('letter', $cv?->paper_size);
        $this->assertSame('large', $cv?->font_scale);
        $this->assertSame('#1D4ED8', $cv?->accent_color);
        $this->assertSame(1, $this->db->table('cv_profiles')->where('cv_id', $cvId)->countAllResults());
        $this->assertSame(1, $this->db->table('cv_experiences')->where('cv_id', $cvId)->countAllResults());

        $this->withSession($_SESSION);
        $preview = $this->get('/dashboard/cv/' . $cvId . '/preview');
        $preview->assertOK();
        $preview->assertSee('font-size:24.20pt');
        $preview->assertSee('#1D4ED8');

        $this->withSession($_SESSION);
        $pages = $this->get('/dashboard/cv/' . $cvId . '/pages');
        $pages->assertOK();
        $pageResult = json_decode($pages->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $pageResult['status']);
        $this->assertGreaterThanOrEqual(1, $pageResult['page_count']);

        $this->withSession($_SESSION);
        $download = $this->get('/dashboard/cv/' . $cvId . '/download');
        $download->assertOK();
        $this->assertSame('application/pdf', $download->response()->getHeaderLine('Content-Type'));
        $this->assertSame('attachment; filename="CV-laporan-kerja.pdf"', $download->response()->getHeaderLine('Content-Disposition'));
        $pdf = $download->response()->getBody();
        $this->assertSame((string) strlen($pdf), $download->response()->getHeaderLine('Content-Length'));
        $this->assertStringContainsString('private', $download->response()->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('no-store', $download->response()->getHeaderLine('Cache-Control'));
        $this->assertSame('nosniff', $download->response()->getHeaderLine('X-Content-Type-Options'));
        $this->assertStringStartsWith('%PDF-', $pdf);

        auth()->logout();
    }

    public function testExtractedPdfTextKeepsNameSectionAndEntryInOrder(): void
    {
        $userId = $this->createUser('pdf-text@example.test', 'pdf-text');
        $cvId = (new CvService())->create($userId, 'CV teks');
        $this->addProfileAndExperience($userId, $cvId);
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $download = $this->get('/dashboard/cv/' . $cvId . '/download');
        $download->assertOK();
        $text = $this->extractPdfText($download->response()->getBody());

        $namePosition = strpos($text, 'Nama Satu');
        $headingPosition = strpos($text, 'Pengalaman');
        $entryPosition = strpos($text, 'Memimpin tim produk');
        $this->assertNotFalse($namePosition);
        $this->assertNotFalse($headingPosition);
        $this->assertNotFalse($entryPosition);
        $this->assertLessThan($headingPosition, $namePosition);
        $this->assertLessThan($entryPosition, $headingPosition);

        auth()->logout();
    }

    public function testUserCannotCountOrDownloadAnotherUsersCv(): void
    {
        $ownerId = $this->createUser('pdf-owner@example.test', 'pdf-owner');
        $otherId = $this->createUser('pdf-other@example.test', 'pdf-other');
        $cvId = (new CvService())->create($ownerId, 'CV privat');
        auth()->loginById($otherId);
        $this->withSession($_SESSION);

        foreach (['pages', 'download'] as $action) {
            $this->withSession($_SESSION);
            try {
                $this->get('/dashboard/cv/' . $cvId . '/' . $action);
                $this->fail('Akses lintas pengguna harus menghasilkan 404.');
            } catch (PageNotFoundException) {
                $this->assertTrue(true);
            }
        }

        auth()->logout();
    }

    public function testLongCvRendersAcrossMultiplePages(): void
    {
        $userId = $this->createUser('pdf-long@example.test', 'pdf-long');
        $cvId = (new CvService())->create($userId, 'CV panjang');
        $builder = new CvBuilderService();
        $builder->saveStep($userId, $cvId, 1, [
            'title' => 'CV panjang',
            'profile' => ['full_name' => 'Nama Panjang'],
            'links' => [],
        ]);
        $experiences = [];
        for ($index = 1; $index <= 24; $index++) {
            $experiences[] = [
                'company' => 'Perusahaan ' . $index,
                'position' => 'Posisi ' . $index,
                'start_date' => '2020-01-01',
                'end_date' => '2022-01-01',
                'bullets' => array_fill(0, 5, 'Menyelesaikan tanggung jawab untuk proyek dan tim produk.'),
            ];
        }
        $builder->saveStep($userId, $cvId, 3, ['experiences' => $experiences]);
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $pages = $this->get('/dashboard/cv/' . $cvId . '/pages');
        $pages->assertOK();
        $result = json_decode($pages->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(1, $result['page_count']);

        auth()->logout();
    }

    public function testEveryThemeProducesReadablePdfBytes(): void
    {
        $userId = $this->createUser('pdf-themes@example.test', 'pdf-themes');
        $cvId = (new CvService())->create($userId, 'CV semua tema');
        $this->addProfileAndExperience($userId, $cvId, 'CV semua tema');
        $renderService = new CvRenderService();
        $builder = new CvBuilderService();
        $pdfRenderer = new DompdfRenderer();

        foreach ((new ThemeRegistry())->all() as $theme) {
            $builder->saveStep($userId, $cvId, 7, ['theme_key' => $theme['key']]);
            $document = $renderService->buildDocument($userId, $cvId);
            $pdf = $pdfRenderer->render(
                $renderService->renderDocumentHtml($document),
                (string) $document['cv']['paper_size'],
            );
            $this->assertStringStartsWith('%PDF-', $pdf, 'PDF tema ' . $theme['key'] . ' tidak valid.');
            $this->assertGreaterThan(1000, strlen($pdf));
        }
    }

    public function testDownloadIsThrottledPerUser(): void
    {
        $userId = $this->createUser('pdf-throttle@example.test', 'pdf-throttle');
        $cvId = (new CvService())->create($userId, 'CV batas unduhan');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $this->assertTrue(service('throttler')->check('cv-pdf-' . $userId, 5, 60, 5));
        $response = $this->get('/dashboard/cv/' . $cvId . '/download');
        $response->assertStatus(429);

        auth()->logout();
    }

    private function addProfileAndExperience(int $userId, string $cvId, string $title = 'CV teks'): void
    {
        $builder = new CvBuilderService();
        $builder->saveStep($userId, $cvId, 1, [
            'title' => $title,
            'profile' => ['full_name' => 'Nama Satu'],
            'links' => [],
        ]);
        $builder->saveStep($userId, $cvId, 2, ['summary' => 'Ringkasan profesional']);
        $builder->saveStep($userId, $cvId, 3, [
            'experiences' => [[
                'company' => 'Perusahaan Contoh',
                'position' => 'Manajer Produk',
                'start_date' => '2022-01-01',
                'end_date' => '2024-01-01',
                'bullets' => ['Memimpin tim produk'],
            ]],
        ]);
    }

    private function extractPdfText(string $pdf): string
    {
        $check = proc_open(['python', '-c', 'import pypdf'], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $checkPipes);
        if (! is_resource($check)) {
            $this->markTestSkipped('Python dengan paket pypdf diperlukan untuk tes ekstraksi PDF.');
        }
        fclose($checkPipes[0]);
        stream_get_contents($checkPipes[1]);
        fclose($checkPipes[1]);
        stream_get_contents($checkPipes[2]);
        fclose($checkPipes[2]);
        if (proc_close($check) !== 0) {
            $this->markTestSkipped('Python dengan paket pypdf diperlukan untuk tes ekstraksi PDF.');
        }

        $path = tempnam(sys_get_temp_dir(), 'cv-maker-pdf-');
        if ($path === false || file_put_contents($path, $pdf) === false) {
            $this->fail('PDF sementara untuk ekstraksi teks tidak dapat disiapkan.');
        }

        try {
            $script = 'import json, sys; from pypdf import PdfReader; print(json.dumps("\\n".join(page.extract_text() or "" for page in PdfReader(sys.argv[1]).pages), ensure_ascii=False))';
            $process = proc_open(['python', '-c', $script, $path], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);
            if (! is_resource($process)) {
                $this->fail('Proses pypdf tidak dapat dijalankan.');
            }

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            $this->assertSame(0, $exitCode, $error);

            return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        } finally {
            unlink($path);
        }
    }

    private function createUser(string $email, string $username): int
    {
        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => $username,
            'email' => $email,
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);

        return $users->getInsertID();
    }
}
