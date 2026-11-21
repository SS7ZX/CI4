<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmailNormalizer;
use CodeIgniter\Shield\Controllers\RegisterController as ShieldRegisterController;

final class AuthRegister extends ShieldRegisterController
{
    public function registerAction(): \CodeIgniter\HTTP\RedirectResponse
    {
        $data = $this->request->getPost();
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = EmailNormalizer::normalize($data['email']);
            $this->request->setGlobal('post', $data);
        }

        return parent::registerAction();
    }
}
