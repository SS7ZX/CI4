<?php

declare(strict_types=1);

use App\Models\Cv;
use App\Services\CvRenderService;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class QuickCvTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testQuickCreateStoresProvidedDataAndOpensReviewStep(): void
    {
        $userId = $this->createUser('quick-cv@example.test', 'quickcv');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        try {
            $dashboard = $this->get('/dashboard');
            $dashboard->assertOK();
            $dashboard->assertSee('Buat CV cepat');
            $dashboard->assertSee(lang('Dashboard.quick_section_profile'));
            $dashboard->assertSee(lang('Dashboard.quick_section_experience'));
            $dashboard->assertSee(lang('Dashboard.quick_section_skills'));
            $dashboard->assertSee('aria-label="' . lang('Dashboard.workspace_summary') . '"');
            $this->assertStringContainsString('x-on:click="$refs.quickCv.open = true"', $dashboard->getBody());
            $dashboard->assertSee('name="email" type="email"');
            $dashboard->assertSee('value="quick-cv@example.test"');

            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/quick', [
                'full_name'  => 'Dina Pratama',
                'headline'   => 'Product Manager',
                'email'      => 'dina@example.test',
                'phone'      => '+62 812 0000 0000',
                'city'       => 'Bandung',
                'summary'    => 'Berpengalaman mengembangkan produk digital.',
                'experience' => "Product Manager di Perusahaan A\nMengelola riset pengguna",
                'education'  => "Universitas Contoh, Sistem Informasi\nKursus manajemen produk",
                'skills'     => "Riset pengguna, Analisis data\nManajemen produk",
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            $cv = (new Cv())->where('user_id', $userId)->first();
            $this->assertNotNull($cv);
            $this->assertSame('CV Product Manager', $cv->title);
            $this->assertSame(8, (int) $cv->last_step);
            $this->assertStringContainsString('/step/8', (string) $response->getRedirectUrl());

            $this->withSession($_SESSION);
            $review = $this->get('/dashboard/cv/' . $cv->id . '/step/8');
            $review->assertOK();
            $review->assertSee(lang('Dashboard.quick_created'));

            $profile = $this->db->table('cv_profiles')->where('cv_id', $cv->id)->get()->getRowArray();
            $this->assertSame('Dina Pratama', $profile['full_name']);
            $this->assertSame('dina@example.test', $profile['email']);
            $this->assertSame('+62 812 0000 0000', $profile['phone']);
            $this->assertSame('Bandung', $profile['city']);
            $this->assertSame('Berpengalaman mengembangkan produk digital.', $profile['summary']);

            $skillGroup = $this->db->table('cv_skill_groups')->where('cv_id', $cv->id)->get()->getRowArray();
            $this->assertSame(
                ['Riset pengguna', 'Analisis data', 'Manajemen produk'],
                json_decode((string) $skillGroup['items'], true, 512, JSON_THROW_ON_ERROR),
            );
            $sections = $this->db->table('cv_custom_sections')
                ->where('cv_id', $cv->id)
                ->orderBy('sort_order')
                ->get()
                ->getResultArray();
            $this->assertSame(lang('CvBuilder.experiences'), $sections[0]['title']);
            $this->assertSame(
                ['Product Manager di Perusahaan A', 'Mengelola riset pengguna'],
                json_decode((string) $sections[0]['items'], true, 512, JSON_THROW_ON_ERROR),
            );
            $this->assertSame(lang('CvBuilder.educations'), $sections[1]['title']);
            $this->assertSame(
                ['Universitas Contoh, Sistem Informasi', 'Kursus manajemen produk'],
                json_decode((string) $sections[1]['items'], true, 512, JSON_THROW_ON_ERROR),
            );

            $rendered = (new CvRenderService())->renderHtml($userId, $cv->id);
            $this->assertStringContainsString('Dina Pratama', $rendered);
            $this->assertStringContainsString('Product Manager di Perusahaan A', $rendered);
            $this->assertStringContainsString('Manajemen produk', $rendered);
        } finally {
            auth()->logout();
        }
    }

    public function testQuickCreateAllowsOptionalDetailsToBeEmpty(): void
    {
        $userId = $this->createUser('quick-minimal@example.test', 'quickminimal');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        try {
            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/quick', [
                'full_name' => 'Raka Santoso',
                'headline'  => 'Analis Data',
            ]);

            $response->assertRedirect();
            $cv = (new Cv())->where('user_id', $userId)->first();
            $this->assertNotNull($cv);

            $profile = $this->db->table('cv_profiles')->where('cv_id', $cv->id)->get()->getRowArray();
            $this->assertSame('Raka Santoso', $profile['full_name']);
            $this->assertNull($profile['phone']);
            $this->assertNull($profile['summary']);
            $this->assertSame(0, $this->db->table('cv_skill_groups')->where('cv_id', $cv->id)->countAllResults());
            $this->assertSame(0, $this->db->table('cv_custom_sections')->where('cv_id', $cv->id)->countAllResults());
        } finally {
            auth()->logout();
        }
    }

    public function testQuickCreateRejectsInvalidEmailWithoutCreatingCv(): void
    {
        $userId = $this->createUser('quick-invalid@example.test', 'quickinvalid');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        try {
            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv/quick', [
                'full_name' => 'Nadia Putri',
                'headline'  => 'Desainer',
                'email'     => 'email-tidak-valid',
            ]);

            $response->assertRedirectTo('/dashboard');
            $response->assertSessionHas('errors');
            $this->assertSame(0, $this->db->table('cvs')->where('user_id', $userId)->countAllResults());
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
