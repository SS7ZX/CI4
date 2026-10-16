<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvProfile as CvProfileEntity;

final class CvProfile extends CvModel
{
    protected $table = 'cv_profiles';
    protected $returnType = CvProfileEntity::class;
    protected $allowedFields = ['cv_id', 'full_name', 'headline', 'email', 'phone', 'city', 'country', 'summary', 'photo_path'];
    protected $fieldRules = [
        'cv_id'     => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'full_name' => 'required|max_length[160]',
        'email'     => 'permit_empty|valid_email|max_length[254]',
    ];
}
