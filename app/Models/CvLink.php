<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvLink as CvLinkEntity;

final class CvLink extends CvModel
{
    protected $table = 'cv_links';
    protected $returnType = CvLinkEntity::class;
    protected $allowedFields = ['cv_id', 'label', 'url', 'sort_order'];
    protected $fieldRules = [
        'cv_id'     => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'label'     => 'required|max_length[80]',
        'url'       => 'required|valid_url_strict|max_length[2048]',
        'sort_order'=> 'required|is_natural',
    ];
}
