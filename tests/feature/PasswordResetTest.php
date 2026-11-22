<?php

declare(strict_types=1);

use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Email\Email as EmailService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;
use Config\Email;

/**
 * @internal
 */
final class PasswordResetTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testRegisteredUserCanRequestAndCompletePasswordReset(): void
    {
        $userId = $this->createUser('reset-password@example.test', 'resetpassword');
        $emailConfig = config(Email::class);
        $emailConfig->protocol = 'smtp';
        $emailConfig->SMTPHost = '';
        $emailConfig->SMTPUser = '';
        $emailConfig->SMTPPass = '';
        $emailConfig->fromEmail = '';

        $request = $this->withHeaders([
            'X-CSRF-TOKEN' => csrf_hash(),
            'Accept'       => 'text/html',
        ])->post('/login/magic-link', [
            'email' => 'reset-password@example.test',
        ]);

        $request->assertRedirect();
        $this->assertStringContainsString('/password/reset-requested', (string) $request->getRedirectUrl());

        $identity = (new UserIdentityModel())
            ->where('user_id', $userId)
            ->where('type', 'password-reset')
            ->first();
        $this->assertNotNull($identity);
        $this->assertIsString($identity->secret);

        $this->withSession($_SESSION);
        $resetPage = $this->get('/password/reset/' . $identity->secret);
        $resetPage->assertOK();
        $resetPage->assertSee(lang('Auth.resetPassword'));

        $reset = $this->withHeaders([
            'X-CSRF-TOKEN' => csrf_hash(),
            'Accept'       => 'text/html',
        ])->post('/password/reset/' . $identity->secret, [
            'password'         => 'ResetBaru!123',
            'password_confirm' => 'ResetBaru!123',
        ]);

        $reset->assertRedirect();
        $this->assertStringContainsString('/login', (string) $reset->getRedirectUrl());

        $userModel = model(UserModel::class);
        $user = $userModel->findById($userId);
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('ResetBaru!123', (string) $user->getPasswordHash()));

        $remaining = (new UserIdentityModel())
            ->where('user_id', $userId)
            ->where('type', 'password-reset')
            ->countAllResults();
        $this->assertSame(0, $remaining);

        $reused = $this->get('/password/reset/' . $identity->secret);
        $reused->assertRedirect();
    }

    public function testUnknownEmailReturnsTheSameResetRequestedResponse(): void
    {
        $response = $this->withHeaders([
            'X-CSRF-TOKEN' => csrf_hash(),
            'Accept'       => 'text/html',
        ])->post('/login/magic-link', [
            'email' => 'tidak-terdaftar@example.test',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/password/reset-requested', (string) $response->getRedirectUrl());
    }

    public function testResetEmailUsesHtmlAndOmitsDebugViewMarkers(): void
    {
        $this->createUser('reset-email@example.test', 'resetemail');

        $emailConfig = config(Email::class);
        $originalConfig = [
            'protocol'   => $emailConfig->protocol,
            'SMTPHost'   => $emailConfig->SMTPHost,
            'SMTPUser'   => $emailConfig->SMTPUser,
            'SMTPPass'   => $emailConfig->SMTPPass,
            'SMTPPort'   => $emailConfig->SMTPPort,
            'fromEmail'  => $emailConfig->fromEmail,
            'fromName'   => $emailConfig->fromName,
        ];
        $emailConfig->protocol = 'smtp';
        $emailConfig->SMTPHost = 'smtp.mail.test';
        $emailConfig->SMTPUser = 'user';
        $emailConfig->SMTPPass = 'password';
        $emailConfig->SMTPPort = 587;
        $emailConfig->fromEmail = 'no-reply@cv-maker.test';
        $emailConfig->fromName = 'CV Maker';

        $resetUrlPrefix = rtrim(config(App::class)->baseURL, '/') . '/password/reset/';
        $email = $this->createMock(EmailService::class);
        $email->expects($this->once())
            ->method('setMailType')
            ->with('html')
            ->willReturnSelf();
        $email->expects($this->once())
            ->method('setMessage')
            ->with($this->callback(static fn (string $message): bool => str_contains($message, '<!doctype html>')
                && str_contains($message, 'href="' . $resetUrlPrefix)
                && ! str_contains($message, 'DEBUG-VIEW')))
            ->willReturnSelf();
        $email->expects($this->once())
            ->method('setAltMessage')
            ->with($this->callback(static fn (string $message): bool => str_contains($message, $resetUrlPrefix)
                && str_contains($message, lang('Auth.passwordResetEmailInstructions'))))
            ->willReturnSelf();
        $email->expects($this->once())->method('send')->with(false)->willReturn(true);
        $email->expects($this->once())->method('clear')->with(true);
        Services::injectMock('email', $email);

        try {
            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/login/magic-link', [
                'email' => 'reset-email@example.test',
            ]);

            $response->assertRedirect();
            $this->assertStringContainsString('/password/reset-requested', (string) $response->getRedirectUrl());
        } finally {
            Services::resetSingle('email');
            foreach ($originalConfig as $key => $value) {
                $emailConfig->{$key} = $value;
            }
        }
    }

    private function createUser(string $email, string $username): int
    {
        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => $username,
            'email'    => $email,
            'password' => 'PasswordLama!123',
        ]);
        $users->save($user);

        return (int) $users->getInsertID();
    }
}
