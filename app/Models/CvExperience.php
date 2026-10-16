<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvExperience as CvExperienceEntity;

final class CvExperience extends CvModel
{
    protected $table = 'cv_experiences';
    protected $returnType = CvExperienceEntity::class;
    protected $allowedFields = ['cv_id', 'company', 'position', 'location', 'start_date', 'end_date', 'is_current', 'bullets', 'sort_order'];
    protected $fieldRules = [
        'cv_id'      => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'company'    => 'required|max_length[160]',
        'position'   => 'required|max_length[160]',
        'start_date' => 'required',
        'sort_order' => 'required|is_natural',
    ];
    protected $jsonFields = ['bullets'];
}
