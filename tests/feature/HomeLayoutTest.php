<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Models\UserModel;

/**
 * @internal
 */
final class HomeLayoutTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testHomePageRendersResponsiveBaseLayout(): void
    {
        $result = $this->get('/');

        $result->assertOK();
        $this->assertStringContainsString('<html lang="id">', $result->getBody());
        $result->assertSee('assets/css/app.css');
        $result->assertSee('assets/js/alpine.min.js');
        $result->assertSee('Buat CV secara bertahap');
        $result->assertSee('Lewati ke konten utama');
        $response = $result->response();
        $response->getCSP()->finalize($response);
        $this->assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString("script-src-attr 'none'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('nosniff', $result->response()->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('same-origin', $result->response()->getHeaderLine('Referrer-Policy'));
        $this->assertSame('camera=(), microphone=(), geolocation=(), payment=()', $result->response()->getHeaderLine('Permissions-Policy'));
        $this->assertSame('same-origin', $result->response()->getHeaderLine('Cross-Origin-Opener-Policy'));
    }

    public function testLogoutIsCsrfProtectedPost(): void
    {
        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => 'logout-accessibility',
            'email' => 'logout-accessibility@example.test',
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);
        auth()->loginById($users->getInsertID());
        $this->withSession($_SESSION);

        $dashboard = $this->get('/dashboard');
        $dashboard->assertOK();
        $this->assertMatchesRegularExpression('/<form action="[^"]*\/logout" method="post"/', $dashboard->getBody());
        $this->assertStringContainsString('csrf_test_name', $dashboard->getBody());

        $this->withSession($_SESSION);
        $logout = $this->post('/logout', [csrf_token() => csrf_hash()]);
        $logout->assertRedirect();
        $this->assertFalse(auth()->loggedIn());
    }
}
