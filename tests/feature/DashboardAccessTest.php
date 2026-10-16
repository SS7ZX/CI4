<?php

declare(strict_types=1);

use App\Services\CvService;
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
        $this->expectException(PageNotFoundException::class);
        $this->get('/dashboard/cv/' . $cvId);
    }

    public function testRegistrationCreatesSessionAndRedirectsToDashboard(): void
    {
        $registerPage = $this->get('/register');
        preg_match('/name=["\']csrf_test_name["\'][^>]*value=["\']([^"\']+)["\']/', $registerPage->getBody(), $csrfMatch);
        $this->assertArrayHasKey(1, $csrfMatch, substr($registerPage->getBody(), 0, 5000));
        $this->withSession($_SESSION);

        $response = $this->post('/register', [
            'csrf_test_name'   => $csrfMatch[1],
            'username'         => 'penggunabaru',
            'email'            => 'baru@example.test',
            'password'         => 'AmanSekali!123',
            'password_confirm' => 'AmanSekali!123',
        ]);

        $response->assertRedirectTo('/dashboard');
        $this->withSession($_SESSION);
        $this->get('/dashboard')->assertOK();
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
