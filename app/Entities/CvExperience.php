<?php

declare(strict_types=1);

namespace App\Entities;

final class CvExperience extends CvEntity
{
    protected array $jsonArrayFields = ['bullets'];

    protected $casts = [
        'is_current' => 'boolean',
        'sort_order' => 'integer',
    ];
}
