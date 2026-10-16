<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvCustomSection as CvCustomSectionEntity;

final class CvCustomSection extends CvModel
{
    protected $table = 'cv_custom_sections';
    protected $returnType = CvCustomSectionEntity::class;
    protected $allowedFields = ['cv_id', 'title', 'items', 'sort_order'];
    protected $fieldRules = [
        'cv_id'     => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'title'     => 'required|max_length[120]',
        'sort_order'=> 'required|is_natural',
    ];
    protected $jsonFields = ['items'];
}
