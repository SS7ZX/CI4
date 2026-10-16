<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvCertification as CvCertificationEntity;

final class CvCertification extends CvModel
{
    protected $table = 'cv_certifications';
    protected $returnType = CvCertificationEntity::class;
    protected $allowedFields = ['cv_id', 'name', 'issuer', 'issued_at', 'expires_at', 'credential_url', 'sort_order'];
    protected $fieldRules = [
        'cv_id'          => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'name'           => 'required|max_length[180]',
        'issuer'         => 'required|max_length[180]',
        'credential_url' => 'permit_empty|valid_url_strict|max_length[2048]',
        'sort_order'     => 'required|is_natural',
    ];
}
