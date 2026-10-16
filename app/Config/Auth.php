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
        'magic-link-login'   => 'auth/forgot_password',
        'magic-link-message' => 'auth/forgot_password_sent',
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
