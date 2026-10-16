<?php

declare(strict_types=1);

namespace App\Entities;

final class CvProject extends CvEntity
{
    protected array $jsonArrayFields = ['bullets', 'tech_stack'];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
