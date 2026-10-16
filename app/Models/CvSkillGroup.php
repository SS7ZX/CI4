<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvSkillGroup as CvSkillGroupEntity;

final class CvSkillGroup extends CvModel
{
    protected $table = 'cv_skill_groups';
    protected $returnType = CvSkillGroupEntity::class;
    protected $allowedFields = ['cv_id', 'name', 'items', 'sort_order'];
    protected $fieldRules = [
        'cv_id'     => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'name'      => 'required|max_length[120]',
        'sort_order'=> 'required|is_natural',
    ];
    protected $jsonFields = ['items'];
}
