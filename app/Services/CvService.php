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

    /**
     * @param array<string, mixed> $data
     */
    public function createQuick(int|string $userId, array $data): string
    {
        $fields = [
            'full_name'  => 160,
            'headline'   => 160,
            'email'      => 254,
            'phone'      => 40,
            'city'       => 120,
            'summary'    => 3000,
            'experience' => 3000,
            'education'  => 3000,
            'skills'     => 3000,
        ];
        $values = [];
        $errors = [];

        foreach ($fields as $field => $maximum) {
            $value = $data[$field] ?? '';
            if (! is_string($value)) {
                $errors[$field] = lang('Dashboard.quick_invalid_text');
                continue;
            }

            $value = trim($value);
            if (mb_strlen($value) > $maximum) {
                $errors[$field] = lang('Dashboard.quick_field_too_long', [$maximum]);
                continue;
            }
            $values[$field] = $value;
        }

        foreach (['full_name', 'headline'] as $requiredField) {
            if (($values[$requiredField] ?? '') === '') {
                $errors[$requiredField] = lang(
                    $requiredField === 'full_name'
                        ? 'Dashboard.quick_name_required'
                        : 'Dashboard.quick_headline_required',
                );
            }
        }

        if (($values['email'] ?? '') !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = lang('Dashboard.quick_email_invalid');
        }

        $experienceItems = $this->quickLines($values['experience'] ?? '', 'experience', 1000, false, $errors);
        $educationItems  = $this->quickLines($values['education'] ?? '', 'education', 1000, false, $errors);
        $skillItems      = $this->quickLines($values['skills'] ?? '', 'skills', 120, true, $errors);

        if ($errors !== []) {
            throw new CvValidationException($errors);
        }

        $database = $this->database ?? db_connect();
        $database->transBegin();

        try {
            $headline = $values['headline'];
            $cvId = $this->cvModel->insert([
                'user_id'    => $userId,
                'title'      => mb_substr('CV ' . $headline, 0, 160),
                'theme_key'  => 'ats-classic',
                'locale'     => 'id',
                'paper_size' => 'a4',
                'font_scale' => 'medium',
                'settings'   => [],
                'last_step'  => 8,
            ]);

            if (! is_string($cvId)) {
                throw new RuntimeException($this->modelError($this->cvModel, 'CV cepat tidak dapat dibuat.'));
            }

            $profileModel = new CvProfile();
            if ($profileModel->insert([
                'cv_id'     => $cvId,
                'full_name' => $values['full_name'],
                'headline'  => $headline,
                'email'     => ($values['email'] ?? '') !== '' ? $values['email'] : null,
                'phone'     => ($values['phone'] ?? '') !== '' ? $values['phone'] : null,
                'city'      => ($values['city'] ?? '') !== '' ? $values['city'] : null,
                'summary'   => ($values['summary'] ?? '') !== '' ? $values['summary'] : null,
            ]) === false) {
                throw new RuntimeException($this->modelError($profileModel, 'Profil CV cepat tidak dapat disimpan.'));
            }

            if ($skillItems !== []) {
                $skillModel = new CvSkillGroup();
                if ($skillModel->insert([
                    'cv_id'      => $cvId,
                    'name'       => lang('CvBuilder.skills'),
                    'items'      => $skillItems,
                    'sort_order' => 0,
                ]) === false) {
                    throw new RuntimeException($this->modelError($skillModel, 'Keahlian CV cepat tidak dapat disimpan.'));
                }
            }

            $customSections = [
                lang('CvBuilder.experiences') => $experienceItems,
                lang('CvBuilder.educations')  => $educationItems,
            ];
            $sectionModel = new CvCustomSection();
            $sortOrder = 0;
            foreach ($customSections as $title => $items) {
                if ($items === []) {
                    continue;
                }

                if ($sectionModel->insert([
                    'cv_id'      => $cvId,
                    'title'      => $title,
                    'items'      => $items,
                    'sort_order' => $sortOrder++,
                ]) === false) {
                    throw new RuntimeException($this->modelError($sectionModel, 'Bagian CV cepat tidak dapat disimpan.'));
                }
            }

            if ($database->transStatus() === false) {
                throw new DatabaseException('CV cepat gagal disimpan ke basis data.');
            }

            $database->transCommit();

            return $cvId;
        } catch (Throwable $exception) {
            $database->transRollback();

            throw $exception;
        }
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

    /**
     * @param array<string, string> $errors
     *
     * @return list<string>
     */
    private function quickLines(
        string $text,
        string $field,
        int $maximumLength,
        bool $splitCommas,
        array &$errors,
    ): array {
        if (trim($text) === '') {
            return [];
        }

        $pattern = $splitCommas ? '/[\r\n,]+/' : '/\r\n|\r|\n/';
        $lines = preg_split($pattern, trim($text));
        if ($lines === false) {
            $errors[$field] = lang('Dashboard.quick_invalid_list');

            return [];
        }

        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > $maximumLength) {
                $errors[$field] = lang('Dashboard.quick_line_too_long', [$maximumLength]);
                continue;
            }
            $items[] = $line;
        }

        if (count($items) > 50) {
            $errors[$field] = lang('Dashboard.quick_too_many_items');

            return [];
        }

        return $items;
    }

    private function modelError(Model $model, string $fallback): string
    {
        $errors = $model->errors();

        return $errors === [] ? $fallback : implode(' ', $errors);
    }
}
