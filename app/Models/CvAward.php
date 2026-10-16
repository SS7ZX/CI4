<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvAward as CvAwardEntity;

final class CvAward extends CvModel
{
    protected $table = 'cv_awards';
    protected $returnType = CvAwardEntity::class;
    protected $allowedFields = ['cv_id', 'title', 'issuer', 'awarded_at', 'description', 'sort_order'];
    protected $fieldRules = [
        'cv_id'     => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'title'     => 'required|max_length[180]',
        'sort_order'=> 'required|is_natural',
    ];
}
