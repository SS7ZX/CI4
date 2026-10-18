<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Controllers\MagicLinkController as ShieldMagicLinkController;
use CodeIgniter\Shield\Validation\ValidationRules;

final class MagicLink extends ShieldMagicLinkController
{
    public function loginAction(): RedirectResponse|string
    {
        $rules = (new ValidationRules())->getLoginRules();
        unset($rules['password']);
        if (! $this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->route('magic-link')->with('errors', $this->validator->getErrors());
        }

        $email = (string) $this->request->getPost('email');
        if ($this->provider->findByCredentials(['email' => $email]) === null) {
            return view(setting('Auth.views')['magic-link-message']);
        }

        return parent::loginAction();
    }
}
