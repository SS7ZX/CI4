<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Validation\ValidationRules;

final class Account extends BaseController
{
    public function editPassword(): string
    {
        return view('auth/change_password', [
            'title' => lang('Auth.changePassword'),
        ]);
    }

    public function updatePassword(): RedirectResponse
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $rules = new ValidationRules();
        $data  = $this->request->getPost();
        $validation = [
            'current_password' => [
                'label'  => 'Auth.currentPassword',
                'rules'  => 'required',
                'errors' => ['required' => 'Auth.currentPasswordRequired'],
            ],
            'password' => [
                ...$rules->getPasswordRules(),
                'rules' => [...$rules->getPasswordRules()['rules'], 'strong_password[]'],
            ],
            'password_confirm' => $rules->getPasswordConfirmRules(),
        ];

        if (! $this->validateData($data, $validation)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $currentPassword = (string) $this->request->getPost('current_password');
        $passwordHash    = $user->getPasswordHash();
        if ($passwordHash === null || ! password_verify($currentPassword, $passwordHash)) {
            return redirect()->back()->withInput()->with('errors', [
                'current_password' => lang('Auth.incorrectCurrentPassword'),
            ]);
        }

        $user->setPassword((string) $this->request->getPost('password'));
        model(UserModel::class)->save($user);
        auth()->logout();

        return redirect()->route('login')->with('success', lang('Auth.passwordChangeSuccess'));
    }
}
