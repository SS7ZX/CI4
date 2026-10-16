<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvProject as CvProjectEntity;

final class CvProject extends CvModel
{
    protected $table = 'cv_projects';
    protected $returnType = CvProjectEntity::class;
    protected $allowedFields = ['cv_id', 'name', 'role', 'url', 'start_date', 'end_date', 'bullets', 'tech_stack', 'sort_order'];
    protected $fieldRules = [
        'cv_id'     => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'name'      => 'required|max_length[160]',
        'url'       => 'permit_empty|valid_url_strict|max_length[2048]',
        'sort_order'=> 'required|is_natural',
    ];
    protected $jsonFields = ['bullets', 'tech_stack'];
}
