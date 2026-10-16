<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Cv as CvEntity;
use App\Models\Cv;
use App\Models\CvAward;
use App\Models\CvCertification;
use App\Models\CvCustomSection;
use App\Models\CvEducation;
use App\Models\CvExperience;
use App\Models\CvLanguage;
use App\Models\CvLink;
use App\Models\CvOrganization;
use App\Models\CvProfile;
use App\Models\CvProject;
use App\Models\CvSectionSetting;
use App\Models\CvSkillGroup;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Model;
use RuntimeException;
use Throwable;

final class CvService
{
    /**
     * @var array<class-string<Model>>
     */
    private const CHILD_MODELS = [
        CvProfile::class,
        CvLink::class,
        CvExperience::class,
        CvEducation::class,
        CvSkillGroup::class,
        CvProject::class,
        CvCertification::class,
        CvOrganization::class,
        CvAward::class,
        CvLanguage::class,
        CvCustomSection::class,
        CvSectionSetting::class,
    ];

    public function __construct(
        private readonly Cv $cvModel = new Cv(),
        private readonly ?BaseConnection $database = null,
    ) {
    }

    public function create(int|string $userId, string $title = 'CV Baru'): string
    {
        $id = $this->cvModel->insert([
            'user_id'    => $userId,
            'title'      => $title,
            'theme_key'  => 'ats-classic',
            'locale'     => 'id',
            'paper_size' => 'a4',
            'font_scale' => 'medium',
            'settings'   => [],
            'last_step'  => 1,
        ]);

        if (! is_string($id)) {
            throw new RuntimeException($this->modelError($this->cvModel, 'CV tidak dapat dibuat.'));
        }

        return $id;
    }

    public function duplicate(int|string $userId, string $cvId): string
    {
        $source = $this->findOwned($userId, $cvId);
        if ($source === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $database = $this->database ?? db_connect();
        $database->transBegin();

        try {
            $newId = $this->cvModel->insert([
                'user_id'      => $userId,
                'title'        => mb_substr($source->title, 0, 150) . ' - salinan',
                'theme_key'    => $source->theme_key,
                'locale'       => $source->locale,
                'paper_size'   => $source->paper_size,
                'font_scale'   => $source->font_scale,
                'accent_color' => $source->accent_color,
                'settings'     => $source->settings,
                'last_step'    => $source->last_step,
            ]);

            if (! is_string($newId)) {
                throw new RuntimeException($this->modelError($this->cvModel, 'CV tidak dapat disalin.'));
            }

            foreach (self::CHILD_MODELS as $modelClass) {
                $childModel = new $modelClass();
                $rows       = $childModel->where('cv_id', $cvId)->asArray()->findAll();

                foreach ($rows as $row) {
                    unset($row['id'], $row['created_at'], $row['updated_at']);
                    $row['cv_id'] = $newId;

                    if ($childModel->insert($row) === false) {
                        throw new RuntimeException($this->modelError($childModel, 'Data CV tidak dapat disalin.'));
                    }
                }
            }

            if ($database->transStatus() === false) {
                throw new DatabaseException('Penyalinan CV gagal disimpan ke basis data.');
            }

            $database->transCommit();

            return $newId;
        } catch (Throwable $exception) {
            $database->transRollback();

            throw $exception;
        }
    }

    public function findOwned(int|string $userId, string $cvId): ?CvEntity
    {
        $cv = $this->cvModel
            ->where('user_id', $userId)
            ->find($cvId);

        return $cv instanceof CvEntity ? $cv : null;
    }

    public function softDelete(int|string $userId, string $cvId): bool
    {
        if ($this->findOwned($userId, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->cvModel->where('user_id', $userId);

        return $this->cvModel->delete($cvId);
    }

    private function modelError(Model $model, string $fallback): string
    {
        $errors = $model->errors();

        return $errors === [] ? $fallback : implode(' ', $errors);
    }
}
