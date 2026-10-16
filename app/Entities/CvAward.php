<?php

declare(strict_types=1);

namespace App\Entities;

final class CvAward extends CvEntity
{
    protected $casts = ['sort_order' => 'integer'];
}
