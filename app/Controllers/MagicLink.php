<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmailNormalizer;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Controllers\MagicLinkController as ShieldMagicLinkController;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Validation\ValidationRules;
use CodeIgniter\I18n\Time;

final class MagicLink extends ShieldMagicLinkController
{
    public function loginAction(): RedirectResponse|string
    {
        $post = $this->request->getPost();
        if (isset($post['email']) && is_string($post['email'])) {
            $post['email'] = EmailNormalizer::normalize($post['email']);
            $this->request->setGlobal('post', $post);
        }

        $rules = (new ValidationRules())->getLoginRules();
        unset($rules['password']);
        if (! $this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->route('magic-link')->with('errors', $this->validator->getErrors());
        }

        $email = EmailNormalizer::normalize((string) $this->request->getPost('email'));
        $this->request->setGlobal('post', [
            ...$this->request->getPost(),
            'email' => $email,
        ]);
        $user = $this->provider->findByCredentials(['email' => $email]);
        if ($user === null) {
            return view(setting('Auth.views')['magic-link-message']);
        }

        $emailConfig = config('Email');
        $smtpConfigured = $emailConfig->isSmtpConfigured();

        if (! $smtpConfigured && ENVIRONMENT === 'development' && $user instanceof User) {
            $token = $this->createDevelopmentMagicLink($user);
            $verificationUrl = same_origin_url(
                (string) url_to('verify-magic-link') . '?token=' . rawurlencode($token),
            );

            return view(setting('Auth.views')['magic-link-message'], [
                'developmentMagicLinkUrl' => $verificationUrl,
            ]);
        }
        if (! $smtpConfigured) {
            log_message('error', 'Permintaan tautan masuk ditolak karena konfigurasi SMTP tidak lengkap.');

            return redirect()
                ->route('magic-link')
                ->with('error', lang('Auth.smtpNotConfigured'));
        }

        return parent::loginAction();
    }

    private function createDevelopmentMagicLink(User $user): string
    {
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        helper('text');
        $token = random_string('crypto', 20);

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
        ]);

        log_message('info', 'Magic-link pengembangan dibuat untuk akun {email}.', [
            'email' => $user->email,
        ]);

        return $token;
    }
}
