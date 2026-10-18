<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\Auth as ShieldAuth;

final class Auth extends ShieldAuth
{
    public array $views = [
        'login'              => 'auth/login',
        'register'           => 'auth/register',
        'layout'             => 'layouts/auth',
        'action_email_2fa'  => '\CodeIgniter\Shield\Views\email_2fa_show',
        'action_email_2fa_verify' => '\CodeIgniter\Shield\Views\email_2fa_verify',
        'action_email_2fa_email' => '\CodeIgniter\Shield\Views\Email\email_2fa_email',
        'action_email_activate_show' => '\CodeIgniter\Shield\Views\email_activate_show',
        'action_email_activate_email' => '\CodeIgniter\Shield\Views\Email\email_activate_email',
        'magic-link-login'   => 'auth/forgot_password',
        'magic-link-message' => 'auth/forgot_password_sent',
        'magic-link-email'   => '\CodeIgniter\Shield\Views\Email\magic_link_email',
    ];

    public array $redirects = [
        'register'          => 'dashboard',
        'login'             => 'dashboard',
        'logout'            => 'login',
        'force_reset'       => 'dashboard/password',
        'permission_denied' => 'dashboard',
        'group_denied'      => 'dashboard',
    ];

    public bool $allowRegistration = true;

    public bool $allowMagicLinkLogins = true;

    public array $validFields = ['email'];
}
