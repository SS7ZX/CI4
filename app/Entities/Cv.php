<?php

declare(strict_types=1);

namespace App\Entities;

final class Cv extends CvEntity
{
    protected array $jsonArrayFields = ['settings'];

    protected $casts = [
        'last_step' => 'integer',
    ];
}
