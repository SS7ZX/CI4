<?php

declare(strict_types=1);

namespace App\Entities;

final class CvSkillGroup extends CvEntity
{
    protected array $jsonArrayFields = ['items'];

    protected $casts = ['sort_order' => 'integer'];
}
