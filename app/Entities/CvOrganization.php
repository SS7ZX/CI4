<?php

declare(strict_types=1);

namespace App\Entities;

final class CvOrganization extends CvEntity
{
    protected array $jsonArrayFields = ['bullets'];

    protected $casts = ['sort_order' => 'integer'];
}
