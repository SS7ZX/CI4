<?php

declare(strict_types=1);

use App\Services\CvService;
use App\Services\ThemeRegistry;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class DashboardAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    protected function setUp(): void
    {
        parent::setUp();

        service('throttler')->remove(md5('0.0.0.0'));
    }

    public function testAuthScreensRenderAndAnonymousDashboardRedirectsToLogin(): void
    {
        $login = $this->get('/login');
        $login->assertOK();
        $login->assertSee('Masuk untuk mengelola CV Anda.');
        $register = $this->get('/register');
        $register->assertOK();
        $register->assertSee('Buat akun untuk menyimpan dan mengelola CV.');
        $forgotPassword = $this->get('/login/magic-link');
        $forgotPassword->assertOK();
        $forgotPassword->assertSee('Lupa kata sandi?');
        $this->get('/dashboard')->assertRedirectTo('/login');
    }

    public function testUserCannotViewAnotherUsersCv(): void
    {
        $userAId = $this->createUser('user-a@example.test', 'user-a');
        $userBId = $this->createUser('user-b@example.test', 'user-b');
        $cvId    = (new CvService())->create($userAId, 'CV privat milik A');

        auth()->loginById($userBId);
        $this->withSession($_SESSION);
        $dashboard = $this->get('/dashboard');
        $dashboard->assertOK();
        $dashboard->assertDontSee('CV privat milik A');

        $this->withSession($_SESSION);
        try {
            $this->get('/dashboard/cv/' . $cvId);
            $this->fail('Akses ke CV pengguna lain harus menghasilkan 404.');
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        } finally {
            auth()->logout();
        }
    }

    public function testRegistrationCreatesSessionAndRedirectsToDashboard(): void
    {
        $csrfToken = $this->csrfTokenFrom('/register');
        $response = $this->post('/register', [
            'csrf_test_name'   => $csrfToken,
            'username'         => 'penggunabaru',
            'email'            => 'baru@example.test',
            'password'         => 'AmanSekali!123',
            'password_confirm' => 'AmanSekali!123',
        ]);

        $response->assertRedirectTo('/dashboard');
        $this->withSession($_SESSION);
        $this->get('/dashboard')->assertOK();
        auth()->logout();
    }

    public function testRegistrationNormalizesEmailAndRejectsCaseInsensitiveDuplicate(): void
    {
        $firstCsrfToken = $this->csrfTokenFrom('/register');
        $firstResponse = $this->post('/register', [
            'csrf_test_name'   => $firstCsrfToken,
            'username'         => 'emailnormal',
            'email'            => '  Normal.User@Example.Test  ',
            'password'         => 'AmanSekali!123',
            'password_confirm' => 'AmanSekali!123',
        ]);

        $firstResponse->assertRedirectTo('/dashboard');
        auth()->logout();

        $identity = $this->db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', 'normal.user@example.test')
            ->get()
            ->getRowArray();
        $this->assertNotEmpty($identity);

        $duplicateCsrfToken = $this->csrfTokenFrom('/register');
        $duplicateResponse = $this->post('/register', [
            'csrf_test_name'   => $duplicateCsrfToken,
            'username'         => 'emailnormal2',
            'email'            => 'NORMAL.USER@EXAMPLE.TEST',
            'password'         => 'AmanSekali!123',
            'password_confirm' => 'AmanSekali!123',
        ]);

        $duplicateResponse->assertRedirect();
        $duplicateResponse->assertSessionHas('errors');
        $this->assertSame(
            1,
            $this->db->table('auth_identities')
                ->where('type', 'email_password')
                ->where('secret', 'normal.user@example.test')
                ->countAllResults(),
        );
    }

    public function testLoginRejectsInvalidCredentialsWithoutChangingSession(): void
    {
        $this->createUser('login@example.test', 'loginuser');
        $csrfToken = $this->csrfTokenFrom('/login');

        $response = $this->post('/login', [
            'csrf_test_name' => $csrfToken,
            'email'          => 'LOGIN@EXAMPLE.TEST',
            'password'       => 'PasswordSalah!123',
        ]);

        $response->assertRedirectTo('/login');
        $response->assertSessionHas('error');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testLoginAcceptsNormalizedEmail(): void
    {
        $this->createUser('normalize-login@example.test', 'normalizelogin');
        $csrfToken = $this->csrfTokenFrom('/login');

        $response = $this->post('/login', [
            'csrf_test_name' => $csrfToken,
            'email'          => '  NORMALIZE-LOGIN@EXAMPLE.TEST ',
            'password'       => 'AmanSekali!123',
        ]);

        $response->assertRedirectTo('/dashboard');
        $this->assertTrue(auth()->loggedIn());
        auth()->logout();
    }

    public function testForgotPasswordDoesNotRevealUnknownAccounts(): void
    {
        $csrfToken = $this->csrfTokenFrom('/login/magic-link');
        $response = $this->post('/login/magic-link', [
            'csrf_test_name' => $csrfToken,
            'email'          => 'unknown@example.test',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/password/reset-requested', (string) $response->getRedirectUrl());
        $response->assertDontSee('unknown@example.test');
    }

    public function testAuthenticatedUserCanChangePassword(): void
    {
        $userId = $this->createUser('change@example.test', 'changepassword');
        auth()->loginById($userId);
        $this->withSession($_SESSION);
        $csrfToken = $this->csrfTokenFrom('/dashboard/password');

        $response = $this->post('/dashboard/password', [
            'csrf_test_name'   => $csrfToken,
            'current_password' => 'AmanSekali!123',
            'password'         => 'LebihAman!456',
            'password_confirm' => 'LebihAman!456',
        ]);

        $response->assertRedirectTo('/login');
        $this->assertTrue(password_verify(
            'LebihAman!456',
            model(UserModel::class)->findByCredentials(['email' => 'change@example.test'])->getPasswordHash(),
        ));
    }

    public function testDashboardCanCreateDuplicateAndSoftDeleteCv(): void
    {
        $userId = $this->createUser('dashboard@example.test', 'dashboarduser');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $createResponse = $this->post('/dashboard/cv', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard'),
            'title'          => 'CV Operasional',
        ]);
        $createResponse->assertRedirect();
        $this->assertStringContainsString('/dashboard/cv/', (string) $createResponse->getRedirectUrl());
        $cvId = (string) $this->db->table('cvs')->where('title', 'CV Operasional')->get()->getRowArray()['id'];

        $this->withSession($_SESSION);
        $dashboard = $this->get('/dashboard');
        $dashboard->assertOK();
        $dashboard->assertSee('CV Operasional');
        $dashboard->assertSee('Lanjutkan');
        $dashboard->assertSee('Pratinjau');
        $dashboard->assertSee('Posisi terakhir: langkah 1 dari 8.');
        $dashboard->assertSee('Struktur ramah ATS');
        $dashboard->assertSee(lang('Dashboard.stat_documents'));
        $dashboard->assertSee(lang('Dashboard.cv_count', [1]));
        $dashboard->assertSee(lang('Dashboard.stat_themes'));
        $dashboard->assertSee((string) count((new ThemeRegistry())->all()));
        $dashboard->assertDontSee('Bagas Pratama');
        $dashboard->assertDontSee('98.4%');
        $dashboard->assertDontSee('AES-256');
        $dashboard->assertDontSee('PDF/A-1b');
        $dashboard->assertSee('Duplikat');
        $dashboard->assertSee('Hapus');

        $duplicateResponse = $this->post('/dashboard/cv/' . $cvId . '/duplicate', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard'),
        ]);
        $duplicateResponse->assertRedirectTo('/dashboard');
        $this->assertSame(
            1,
            $this->db->table('cvs')->where('title', 'CV Operasional - salinan')->countAllResults(),
        );

        $deleteResponse = $this->post('/dashboard/cv/' . $cvId . '/delete', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard'),
        ]);
        $deleteResponse->assertRedirectTo('/dashboard');
        $deletedCv = $this->db->table('cvs')->where('id', $cvId)->get()->getRowArray();
        $this->assertNotNull($deletedCv['deleted_at']);
        auth()->logout();
    }

    private function csrfTokenFrom(string $path): string
    {
        $page = $this->get($path);
        preg_match('/name=["\']csrf_test_name["\'][^>]*value=["\']([^"\']+)["\']/', $page->getBody(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->withSession($_SESSION);

        return $matches[1];
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
