<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmailNormalizer;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Validation\ValidationRules;
use Config\Email;

final class PasswordReset extends BaseController
{
    private const RESET_IDENTITY_TYPE = 'password-reset';
    private const RESET_LIFETIME_SECONDS = 3600;

    public function forgotView(): string
    {
        return view('auth/forgot_password', [
            'title' => lang('Auth.forgotPassword'),
        ]);
    }

    public function requestReset(): RedirectResponse
    {
        $email = '';
        $postedEmail = $this->request->getPost('email');
        if (is_string($postedEmail)) {
            $email = EmailNormalizer::normalize($postedEmail);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('magic-link')->withInput()->with('errors', [
                'email' => lang('Auth.emailInvalid'),
            ]);
        }

        $user = model(UserModel::class)->findByCredentials(['email' => $email]);
        if ($user instanceof User) {
            $this->createAndSendReset($user);
        }

        return redirect()
            ->route('password-reset-requested')
            ->with('resetEmail', $email);
    }

    public function resetRequested(): string
    {
        return view('auth/forgot_password_sent', [
            'title'                 => lang('Auth.forgotPassword'),
            'developmentResetUrl'   => session('developmentResetUrl'),
            'resetRequestCompleted' => true,
        ]);
    }

    public function resetView(string $token): string|RedirectResponse
    {
        $identity = $this->findValidResetIdentity($token);
        if ($identity === null) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.resetTokenExpired'));
        }

        return view('auth/reset_password', [
            'title' => lang('Auth.resetPassword'),
            'token' => $token,
        ]);
    }

    public function resetAction(string $token): RedirectResponse
    {
        $identity = $this->findValidResetIdentity($token);
        if ($identity === null) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.resetTokenExpired'));
        }

        $passwordRules = (new ValidationRules())->getPasswordRules();
        $validation = [
            'password'         => $passwordRules,
            'password_confirm' => (new ValidationRules())->getPasswordConfirmRules(),
        ];

        if (! $this->validateData($this->request->getPost(), $validation)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $user = model(UserModel::class)->findById((int) $identity->user_id);
        if (! $user instanceof User) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.resetTokenExpired'));
        }

        $user->setPassword((string) $this->request->getPost('password'));
        if (! model(UserModel::class)->save($user)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', [
                    'password' => lang('Auth.incorrectPassword'),
                ]);
        }

        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, self::RESET_IDENTITY_TYPE);
        $identityModel->deleteIdentitiesByType($user, 'magic-link');

        auth()->logout();

        return redirect()
            ->route('login')
            ->with('success', lang('Auth.passwordResetSuccess'));
    }

    private function createAndSendReset(User $user): void
    {
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, self::RESET_IDENTITY_TYPE);

        helper('text');
        $token = random_string('crypto', 32);
        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => self::RESET_IDENTITY_TYPE,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(self::RESET_LIFETIME_SECONDS),
        ]);

        $resetUrl = (string) url_to('password-reset', $token);

        $emailConfig = config(Email::class);
        if (! $emailConfig->isSmtpConfigured()) {
            if (ENVIRONMENT === 'development') {
                session()->set('developmentResetUrl', $resetUrl);
            }

            log_message('error', 'Permintaan reset password diterima, tetapi SMTP belum dikonfigurasi.');
            return;
        }

        $email = service('email');
        $email->setFrom($emailConfig->fromEmail, $emailConfig->fromName);
        $email->setTo((string) $user->email);
        $email->setSubject(lang('Auth.passwordResetSubject'));
        $email->setMessage(view('auth/password_reset_email', [
            'resetUrl'       => $resetUrl,
            'lifetimeMinutes' => self::RESET_LIFETIME_SECONDS / 60,
        ], ['debug' => false]));
        $email->setMailType('html');
        $email->setAltMessage(implode("\n\n", [
            lang('Auth.passwordResetEmailGreeting'),
            lang('Auth.passwordResetEmailInstructions'),
            lang('Auth.resetPassword') . ': ' . $resetUrl,
            lang('Auth.passwordResetEmailExpiry', [
                (string) (self::RESET_LIFETIME_SECONDS / 60),
            ]),
        ]));

        if (! $email->send(false)) {
            log_message('error', 'Email reset password gagal dikirim untuk akun terdaftar.');
        }
        $email->clear(true);
    }

    private function findValidResetIdentity(string $token): ?\CodeIgniter\Shield\Entities\UserIdentity
    {
        if (strlen($token) < 32) {
            return null;
        }

        $identity = model(UserIdentityModel::class)
            ->getIdentityBySecret(self::RESET_IDENTITY_TYPE, $token);
        if ($identity === null) {
            return null;
        }

        if (! $identity->expires instanceof Time || $identity->expires->isPast()) {
            model(UserIdentityModel::class)
                ->where('id', $identity->id)
                ->delete();
            return null;
        }

        return $identity;
    }
}
