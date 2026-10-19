<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ThemeRegistry;

class Home extends BaseController
{
    public function index(): string
    {
        return view('home', [
            'themes' => (new ThemeRegistry())->all(),
        ]);
    }
}
