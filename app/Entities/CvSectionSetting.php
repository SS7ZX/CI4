<?php

declare(strict_types=1);

namespace App\Entities;

final class CvSectionSetting extends CvEntity
{
    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];
}
