<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvLanguage as CvLanguageEntity;

final class CvLanguage extends CvModel
{
    protected $table = 'cv_languages';
    protected $returnType = CvLanguageEntity::class;
    protected $allowedFields = ['cv_id', 'name', 'level', 'sort_order'];
    protected $fieldRules = [
        'cv_id'     => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'name'      => 'required|max_length[120]',
        'level'     => 'required|in_list[basic,conversational,professional,native]',
        'sort_order'=> 'required|is_natural',
    ];
}
