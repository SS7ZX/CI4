<?php

declare(strict_types=1);

use App\Services\CvBuilderService;
use App\Services\CvRenderService;
use App\Services\CvService;
use App\Services\CvValidationException;
use App\Services\ThemeRegistry;
use App\Libraries\Pdf\DompdfRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class CvThemeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testThemeChangeKeepsCvDataAndPreviewUsesAtsSafeMarkup(): void
    {
        $userId  = $this->createUser('theme-owner@example.test', 'theme-owner');
        $cvId    = (new CvService())->create($userId, 'CV tanpa perubahan');
        $builder = new CvBuilderService();
        $builder->saveStep($userId, $cvId, 1, [
            'title'   => 'CV tanpa perubahan',
            'profile' => [
                'full_name' => 'Nama <script>alert(1)</script>',
                'headline'  => 'Analis',
            ],
            'links' => [],
        ]);
        $builder->saveStep($userId, $cvId, 3, [
            'experiences' => [[
                'company'    => 'Perusahaan',
                'position'   => 'Analis',
                'start_date' => '2024-01-01',
                'end_date'   => '2025-01-01',
                'bullets'    => ['Menyusun laporan'],
            ]],
        ]);

        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $editor = $this->get('/dashboard/cv/' . $cvId . '/step/7');
        $editor->assertOK();
        $editor->assertSee('ATS Klasik');
        $editor->assertSee('Tidak dioptimalkan untuk ATS');
        preg_match_all('/class="theme-preview(?:\\s|")/', $editor->getBody(), $matches);
        $this->assertSame(11, count($matches[0]));
        $editor->assertSee('Label ramah ATS menjelaskan struktur dokumen, bukan jaminan lolos seleksi.');

        $preview = $this->get('/dashboard/cv/' . $cvId . '/preview/ats-classic');
        $preview->assertOK();
        $html = $preview->getBody();
        $this->assertDoesNotMatchRegularExpression('/<(?:table|img|svg)\b/i', $html);
        $this->assertStringContainsString('Menyusun laporan', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame('ats-classic', (new CvService())->findOwned($userId, $cvId)?->theme_key);
        $document = (new CvRenderService())->buildDocument($userId, $cvId);
        $this->assertSame(
            ['profile', 'summary', 'experience', 'education', 'skills', 'links', 'projects', 'certifications', 'organizations', 'awards', 'languages', 'custom_sections'],
            array_column($document['sections'], 'key'),
        );
        $this->assertSame('Nama <script>alert(1)</script>', $document['profile']['full_name']);
        $this->assertStringStartsWith('%PDF-', (new DompdfRenderer())->render($html));

        $builder->saveStep($userId, $cvId, 6, [
            'projects'        => [],
            'certifications'  => [],
            'organizations'   => [],
            'awards'           => [],
            'languages'        => [],
            'custom_sections'  => [],
            'sections'         => [
                ['section_key' => 'experience', 'sort_order' => 0, 'is_visible' => false],
                ['section_key' => 'profile', 'sort_order' => 1, 'is_visible' => true],
                ['section_key' => 'summary', 'sort_order' => 2, 'is_visible' => true],
            ],
        ]);
        $orderedDocument = (new CvRenderService())->buildDocument($userId, $cvId);
        $this->assertSame(
            ['experience', 'profile', 'summary'],
            array_slice(array_column($orderedDocument['sections'], 'key'), 0, 3),
        );
        $this->assertFalse($orderedDocument['sections'][0]['visible']);

        $this->withSession($_SESSION);
        $autosave = $this->withBodyFormat('json')
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'Accept' => 'application/json'])
            ->post('/dashboard/cv/' . $cvId . '/autosave/7', ['theme_key' => 'professional']);
        $autosave->assertOK();
        $this->assertSame('professional', (new CvService())->findOwned($userId, $cvId)?->theme_key);
        $this->assertSame(1, $this->db->table('cv_profiles')->where('cv_id', $cvId)->countAllResults());
        $this->assertSame(1, $this->db->table('cv_experiences')->where('cv_id', $cvId)->countAllResults());

        $this->withSession($_SESSION);
        $currentPreview = $this->get('/dashboard/cv/' . $cvId . '/preview');
        $currentPreview->assertOK();
        $this->assertStringContainsString('cv-document--sidebar', $currentPreview->getBody());
        auth()->logout();
    }

    public function testUnknownThemeIsRejectedBySaveAndPreview(): void
    {
        $userId = $this->createUser('theme-invalid@example.test', 'theme-invalid');
        $cvId   = (new CvService())->create($userId, 'CV');
        $this->assertFalse((new ThemeRegistry())->has('unknown-theme'));

        try {
            (new CvBuilderService())->saveStep($userId, $cvId, 7, ['theme_key' => 'unknown-theme']);
            $this->fail('Tema yang tidak terdaftar harus ditolak.');
        } catch (CvValidationException $exception) {
            $this->assertArrayHasKey('theme_key', $exception->errors());
        }

        auth()->loginById($userId);
        $this->withSession($_SESSION);
        try {
            $this->get('/dashboard/cv/' . $cvId . '/preview/unknown-theme');
            $this->fail('Pratinjau tema yang tidak terdaftar harus menghasilkan 404.');
        } catch (PageNotFoundException) {
            $this->assertTrue(true);
        }
        auth()->logout();
    }

    private function createUser(string $email, string $username): int
    {
        $users = model(UserModel::class);
        $user  = $users->createNewUser([
            'username' => $username,
            'email'    => $email,
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);

        return $users->getInsertID();
    }
}
