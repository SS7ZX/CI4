<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvOrganization as CvOrganizationEntity;

final class CvOrganization extends CvModel
{
    protected $table = 'cv_organizations';
    protected $returnType = CvOrganizationEntity::class;
    protected $allowedFields = ['cv_id', 'organization', 'role', 'start_date', 'end_date', 'bullets', 'sort_order'];
    protected $fieldRules = [
        'cv_id'        => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'organization' => 'required|max_length[180]',
        'role'         => 'required|max_length[120]',
        'start_date'   => 'required',
        'sort_order'   => 'required|is_natural',
    ];
    protected $jsonFields = ['bullets'];
}
