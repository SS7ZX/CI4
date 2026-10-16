<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Cv as CvEntity;

final class Cv extends CvModel
{
    protected $table = 'cvs';
    protected $returnType = CvEntity::class;
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'user_id',
        'title',
        'theme_key',
        'locale',
        'paper_size',
        'font_scale',
        'accent_color',
        'settings',
        'last_step',
    ];
    protected $fieldRules = [
        'user_id'    => 'required|is_natural_no_zero',
        'title'      => 'required|max_length[160]',
        'theme_key'  => 'required|max_length[80]',
        'locale'     => 'required|max_length[12]',
        'paper_size' => 'required|in_list[a4,letter]',
        'font_scale' => 'required|in_list[small,medium,large]',
        'last_step'  => 'required|greater_than_equal_to[1]|less_than_equal_to[8]',
    ];
    protected $jsonFields = ['settings'];
}
