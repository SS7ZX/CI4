<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvEducation as CvEducationEntity;

final class CvEducation extends CvModel
{
    protected $table = 'cv_educations';
    protected $returnType = CvEducationEntity::class;
    protected $allowedFields = ['cv_id', 'institution', 'degree', 'field_of_study', 'gpa', 'start_date', 'end_date', 'description', 'sort_order'];
    protected $fieldRules = [
        'cv_id'          => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'institution'    => 'required|max_length[200]',
        'degree'         => 'required|max_length[120]',
        'field_of_study' => 'required|max_length[160]',
        'start_date'     => 'required',
        'sort_order'     => 'required|is_natural',
    ];
}
