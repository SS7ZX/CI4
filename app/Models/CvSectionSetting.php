<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CvSectionSetting as CvSectionSettingEntity;

final class CvSectionSetting extends CvModel
{
    protected $table = 'cv_section_settings';
    protected $returnType = CvSectionSettingEntity::class;
    protected $allowedFields = ['cv_id', 'section_key', 'is_visible', 'sort_order'];
    protected $fieldRules = [
        'cv_id'       => 'required|regex_match[/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}\z/]',
        'section_key' => 'required|max_length[80]',
        'sort_order'  => 'required|is_natural',
    ];
}
