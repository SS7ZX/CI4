<?php

declare(strict_types=1);

namespace App\Entities;

final class CvCustomSection extends CvEntity
{
    protected array $jsonArrayFields = ['items'];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
