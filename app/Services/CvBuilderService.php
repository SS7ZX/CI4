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
use DateTimeImmutable;
use Throwable;

final class CvBuilderService
{
    private const SECTION_KEYS = [
        'profile',
        'links',
        'summary',
        'experience',
        'education',
        'skills',
        'projects',
        'certifications',
        'organizations',
        'awards',
        'languages',
        'custom_sections',
    ];

    private readonly ThemeRegistry $themeRegistry;

    /**
     * @var array<string, array{class: class-string<Model>, fields: array<string, array{required: bool, max: int|null, type: string}>}>
     */
    private const COLLECTIONS = [
        'links' => [
            'class'  => CvLink::class,
            'fields' => [
                'label' => ['required' => true, 'max' => 80, 'type' => 'text'],
                'url'   => ['required' => true, 'max' => 2048, 'type' => 'url'],
            ],
        ],
        'experiences' => [
            'class'  => CvExperience::class,
            'fields' => [
                'company'    => ['required' => true, 'max' => 160, 'type' => 'text'],
                'position'   => ['required' => true, 'max' => 160, 'type' => 'text'],
                'location'   => ['required' => false, 'max' => 160, 'type' => 'text'],
                'start_date' => ['required' => true, 'max' => null, 'type' => 'date'],
                'end_date'   => ['required' => false, 'max' => null, 'type' => 'date'],
                'is_current' => ['required' => false, 'max' => null, 'type' => 'boolean'],
                'bullets'    => ['required' => false, 'max' => 500, 'type' => 'lines'],
            ],
        ],
        'educations' => [
            'class'  => CvEducation::class,
            'fields' => [
                'institution'    => ['required' => true, 'max' => 200, 'type' => 'text'],
                'degree'         => ['required' => true, 'max' => 120, 'type' => 'text'],
                'field_of_study' => ['required' => true, 'max' => 160, 'type' => 'text'],
                'gpa'            => ['required' => false, 'max' => 20, 'type' => 'text'],
                'start_date'     => ['required' => true, 'max' => null, 'type' => 'date'],
                'end_date'       => ['required' => false, 'max' => null, 'type' => 'date'],
                'description'    => ['required' => false, 'max' => 3000, 'type' => 'text'],
            ],
        ],
        'skill_groups' => [
            'class'  => CvSkillGroup::class,
            'fields' => [
                'name'  => ['required' => true, 'max' => 120, 'type' => 'text'],
                'items' => ['required' => true, 'max' => 120, 'type' => 'lines'],
            ],
        ],
        'projects' => [
            'class'  => CvProject::class,
            'fields' => [
                'name'       => ['required' => true, 'max' => 160, 'type' => 'text'],
                'role'       => ['required' => false, 'max' => 120, 'type' => 'text'],
                'url'        => ['required' => false, 'max' => 2048, 'type' => 'url'],
                'start_date' => ['required' => false, 'max' => null, 'type' => 'date'],
                'end_date'   => ['required' => false, 'max' => null, 'type' => 'date'],
                'bullets'    => ['required' => false, 'max' => 500, 'type' => 'lines'],
                'tech_stack' => ['required' => false, 'max' => 80, 'type' => 'lines'],
            ],
        ],
        'certifications' => [
            'class'  => CvCertification::class,
            'fields' => [
                'name'           => ['required' => true, 'max' => 180, 'type' => 'text'],
                'issuer'         => ['required' => true, 'max' => 180, 'type' => 'text'],
                'issued_at'      => ['required' => false, 'max' => null, 'type' => 'date'],
                'expires_at'     => ['required' => false, 'max' => null, 'type' => 'date'],
                'credential_url' => ['required' => false, 'max' => 2048, 'type' => 'url'],
            ],
        ],
        'organizations' => [
            'class'  => CvOrganization::class,
            'fields' => [
                'organization' => ['required' => true, 'max' => 180, 'type' => 'text'],
                'role'         => ['required' => true, 'max' => 120, 'type' => 'text'],
                'start_date'   => ['required' => true, 'max' => null, 'type' => 'date'],
                'end_date'     => ['required' => false, 'max' => null, 'type' => 'date'],
                'bullets'      => ['required' => false, 'max' => 500, 'type' => 'lines'],
            ],
        ],
        'awards' => [
            'class'  => CvAward::class,
            'fields' => [
                'title'       => ['required' => true, 'max' => 180, 'type' => 'text'],
                'issuer'      => ['required' => false, 'max' => 180, 'type' => 'text'],
                'awarded_at'  => ['required' => false, 'max' => null, 'type' => 'date'],
                'description' => ['required' => false, 'max' => 3000, 'type' => 'text'],
            ],
        ],
        'languages' => [
            'class'  => CvLanguage::class,
            'fields' => [
                'name'  => ['required' => true, 'max' => 120, 'type' => 'text'],
                'level' => ['required' => true, 'max' => 24, 'type' => 'enum'],
            ],
        ],
        'custom_sections' => [
            'class'  => CvCustomSection::class,
            'fields' => [
                'title' => ['required' => true, 'max' => 120, 'type' => 'text'],
                'items' => ['required' => true, 'max' => 1000, 'type' => 'lines'],
            ],
        ],
    ];

    public function __construct(
        private readonly Cv $cvModel = new Cv(),
        private readonly ?BaseConnection $database = null,
        ?ThemeRegistry $themeRegistry = null,
    ) {
        $this->themeRegistry = $themeRegistry ?? new ThemeRegistry();
    }

    public function load(int|string $userId, string $cvId): array
    {
        $cv = $this->findOwned($userId, $cvId);
        if ($cv === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $data = ['cv' => $cv];
        foreach (self::COLLECTIONS as $key => $definition) {
            $data[$key] = [];
        }
        $data['profile'] = null;
        $data['sections'] = [];

        $this->loadChildren($cvId, $data);

        return $data;
    }

    /**
     * Load all heterogeneous child records in one bound UNION query.
     *
     * @param array<string, mixed> $data
     */
    private function loadChildren(string $cvId, array &$data): void
    {
        $database = $this->database ?? db_connect();
        $tables = [
            'profile' => [
                'model' => CvProfile::class,
                'fields' => ['full_name', 'headline', 'email', 'phone', 'city', 'country', 'summary', 'photo_path'],
                'sort' => false,
            ],
            'sections' => [
                'model' => CvSectionSetting::class,
                'fields' => ['section_key', 'is_visible', 'sort_order'],
                'sort' => true,
            ],
        ];
        foreach (self::COLLECTIONS as $key => $definition) {
            $tables[$key] = [
                'model' => $definition['class'],
                'fields' => array_keys($definition['fields']),
                'sort' => isset($definition['fields']['sort_order']),
            ];
        }

        $queries = [];
        $bindings = [];
        foreach ($tables as $collection => $definition) {
            $modelClass = $definition['model'];
            $model = new $modelClass();
            $table = $database->protectIdentifiers(
                $database->prefixTable($model->builder()->getTable()),
                false,
                true,
            );
            $columns = ['id', 'cv_id', ...$definition['fields'], 'created_at', 'updated_at'];
            $columns = array_values(array_unique($columns));
            if ($database->DBDriver === 'Postgre') {
                $payload = 'to_jsonb(child)::text';
            } else {
                $jsonPairs = [];
                foreach ($columns as $column) {
                    $jsonPairs[] = "'" . $column . "'";
                    $jsonPairs[] = 'child.' . $database->protectIdentifiers($column);
                }
                $payload = 'json_object(' . implode(', ', $jsonPairs) . ')';
            }
            $sortOrder = $definition['sort']
                ? 'child.' . $database->protectIdentifiers('sort_order')
                : 'NULL';
            $queries[] = sprintf(
                "SELECT '%s' AS collection, %s AS sort_order, %s AS payload FROM %s child WHERE %s = ?",
                $collection,
                $sortOrder,
                $payload,
                $table,
                'child.' . $database->protectIdentifiers('cv_id'),
            );
            $bindings[] = $cvId;
        }

        $result = $database->query(
            implode(' UNION ALL ', $queries) . ' ORDER BY collection, sort_order',
            $bindings,
        );

        foreach ($result->getResultArray() as $record) {
            $collection = $record['collection'];
            $row = json_decode((string) $record['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($row)) {
                throw new DatabaseException('Data bagian CV tidak valid.');
            }

            if ($collection === 'profile') {
                $data['profile'] = new \App\Entities\CvProfile($row);
                continue;
            }
            if ($collection === 'sections') {
                $data['sections'][] = new \App\Entities\CvSectionSetting($row);
                continue;
            }

            $modelClass = self::COLLECTIONS[$collection]['class'];
            $entityClass = 'App\\Entities\\' . substr($modelClass, strrpos($modelClass, '\\') + 1);
            $entity = new $entityClass($row);
            $data[$collection][] = $entity;
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    public function saveStep(int|string $userId, string $cvId, int $step, array $payload): array
    {
        if ($this->findOwned($userId, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($step < 1 || $step > 8) {
            throw new CvValidationException(['step' => lang('CvBuilder.invalid_step')]);
        }

        $errors = match ($step) {
            1 => $this->validateProfile($payload),
            2 => $this->validateSummary($payload),
            3 => $this->validateRows($payload, 'experiences'),
            4 => $this->validateRows($payload, 'educations'),
            5 => $this->validateRows($payload, 'skill_groups'),
            6 => $this->validateAdditional($payload),
            7 => $this->validateTheme($payload),
            8 => $this->validateDocumentSettings($cvId, $payload),
            default => ['step' => lang('CvBuilder.invalid_step')],
        };

        if ($errors !== []) {
            throw new CvValidationException($errors);
        }

        $database = $this->database ?? db_connect();
        $database->transBegin();

        try {
            switch ($step) {
                case 1:
                    $this->saveProfileStep($cvId, $payload);
                    break;
                case 2:
                    $this->saveSummaryStep($cvId, $payload);
                    break;
                case 3:
                    $this->replaceRows($cvId, 'experiences', $payload['experiences']);
                    break;
                case 4:
                    $this->replaceRows($cvId, 'educations', $payload['educations']);
                    break;
                case 5:
                    $this->replaceRows($cvId, 'skill_groups', $payload['skill_groups']);
                    break;
                case 6:
                    $this->saveAdditionalStep($cvId, $payload);
                    break;
                case 7:
                    $this->saveThemeStep($cvId, $payload);
                    break;
                case 8:
                    $this->saveDocumentSettings($cvId, $payload);
                    break;
            }

            if (! $this->cvModel->update($cvId, ['last_step' => $step])) {
                throw new DatabaseException('Langkah terakhir CV tidak dapat disimpan.');
            }

            if ($database->transStatus() === false) {
                throw new DatabaseException('Autosave CV gagal disimpan.');
            }

            $database->transCommit();

            return [];
        } catch (Throwable $exception) {
            $database->transRollback();

            throw $exception;
        }
    }

    public function findOwned(int|string $userId, string $cvId): ?CvEntity
    {
        $cv = $this->cvModel->where('user_id', $userId)->find($cvId);

        return $cv instanceof CvEntity ? $cv : null;
    }

    public function updatePhotoPath(int|string $userId, string $cvId, string $photoPath): ?string
    {
        if ($this->findOwned($userId, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model   = new CvProfile();
        $profile = $model->where('cv_id', $cvId)->first();
        if ($profile === null) {
            throw new CvValidationException(['profile.full_name' => lang('CvBuilder.name_before_photo')]);
        }

        $oldPath = $profile->photo_path;
        if (! $model->update($profile->id, ['photo_path' => $photoPath])) {
            throw new DatabaseException('Foto profil tidak dapat disimpan.');
        }

        return is_string($oldPath) ? $oldPath : null;
    }

    public function photoPath(int|string $userId, string $cvId): string
    {
        if ($this->findOwned($userId, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $profile = (new CvProfile())->where('cv_id', $cvId)->first();
        if ($profile === null || ! is_string($profile->photo_path) || $profile->photo_path === '') {
            throw PageNotFoundException::forPageNotFound();
        }

        return $profile->photo_path;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function validateProfile(array $payload): array
    {
        $profile = $payload['profile'] ?? null;
        if (! is_array($profile)) {
            return ['profile' => lang('CvBuilder.invalid_profile')];
        }

        $errors = [];
        $this->validateText($errors, $payload, 'title', 'title', true, 160);
        $this->validateText($errors, $profile, 'full_name', 'profile.full_name', true, 160);
        foreach ([
            'headline' => 160,
            'email'    => 254,
            'phone'    => 40,
            'city'     => 120,
            'country'  => 120,
        ] as $field => $maximum) {
            $this->validateText($errors, $profile, $field, 'profile.' . $field, false, $maximum);
        }
        if (is_string($profile['email'] ?? null) && $profile['email'] !== '' && filter_var($profile['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['profile.email'] = lang('CvBuilder.invalid_email');
        }
        $this->validateRows($payload, 'links', $errors);

        return $errors;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function validateSummary(array $payload): array
    {
        $summary = $payload['summary'] ?? null;

        return is_string($summary) && mb_strlen($summary) <= 3000
            ? []
            : ['summary' => lang('CvBuilder.summary_too_long')];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $errors
     *
     * @return array<string, string>
     */
    private function validateRows(array $payload, string $collection, array &$errors = []): array
    {
        $rows = $payload[$collection] ?? [];
        if (! is_array($rows) || ! array_is_list($rows)) {
            $errors[$collection] = lang('CvBuilder.invalid_list');

            return $errors;
        }

        if (count($rows) > 50) {
            $errors[$collection] = lang('CvBuilder.too_many_items');

            return $errors;
        }

        $definition = self::COLLECTIONS[$collection];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors[$collection . '.' . $index] = lang('CvBuilder.invalid_item');
                continue;
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            foreach ($definition['fields'] as $field => $rules) {
                $value = $row[$field] ?? null;
                $path  = $collection . '.' . $index . '.' . $field;

                if ($field === 'end_date' && $collection === 'experiences' && $this->isTruthy($row['is_current'] ?? false)) {
                    continue;
                }
                if ($rules['required'] && $this->isEmptyValue($value)) {
                    $errors[$path] = lang('CvBuilder.field_required');
                    continue;
                }
                if ($this->isEmptyValue($value)) {
                    continue;
                }

                if ($rules['type'] === 'text' && (! is_string($value) || mb_strlen($value) > $rules['max'])) {
                    $errors[$path] = lang('CvBuilder.field_too_long', [$rules['max']]);
                } elseif ($rules['type'] === 'url' && (! is_string($value) || mb_strlen($value) > $rules['max'] || ! $this->isHttpUrl($value))) {
                    $errors[$path] = lang('CvBuilder.invalid_url');
                } elseif ($rules['type'] === 'date' && ! $this->isValidDate($value)) {
                    $errors[$path] = lang('CvBuilder.invalid_date');
                } elseif ($rules['type'] === 'lines' && (! is_array($value) || ! $this->validLines($value, $rules['max']))) {
                    $errors[$path] = lang('CvBuilder.invalid_list');
                } elseif ($rules['type'] === 'enum' && ! in_array($value, ['basic', 'conversational', 'professional', 'native'], true)) {
                    $errors[$path] = lang('CvBuilder.invalid_value');
                } elseif ($rules['type'] === 'boolean' && ! in_array($value, [true, false, 0, 1, '0', '1', null], true)) {
                    $errors[$path] = lang('CvBuilder.invalid_value');
                }
            }

            $datePairs = [
                'experiences'    => ['start_date', 'end_date'],
                'educations'     => ['start_date', 'end_date'],
                'projects'       => ['start_date', 'end_date'],
                'organizations'  => ['start_date', 'end_date'],
                'certifications' => ['issued_at', 'expires_at'],
            ];
            if (isset($datePairs[$collection])) {
                [$startField, $endField] = $datePairs[$collection];
                if (
                    ! ($collection === 'experiences' && $this->isTruthy($row['is_current'] ?? false))
                    && isset($row[$startField], $row[$endField])
                    && $row[$startField] !== ''
                    && $row[$endField] !== ''
                    && $row[$endField] < $row[$startField]
                ) {
                    $errors[$collection . '.' . $index . '.' . $endField] = lang('CvBuilder.end_before_start');
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function validateAdditional(array $payload): array
    {
        $errors = [];
        foreach (['projects', 'certifications', 'organizations', 'awards', 'languages', 'custom_sections'] as $collection) {
            $this->validateRows($payload, $collection, $errors);
        }

        $sections = $payload['sections'] ?? [];
        if (! is_array($sections) || ! array_is_list($sections) || count($sections) > count(self::SECTION_KEYS)) {
            $errors['sections'] = lang('CvBuilder.invalid_sections');

            return $errors;
        }

        $seen = [];
        foreach ($sections as $index => $section) {
            if (! is_array($section)
                || ! isset($section['section_key'])
                || ! in_array($section['section_key'], self::SECTION_KEYS, true)
                || isset($seen[$section['section_key']])
                || (isset($section['is_visible']) && ! in_array($section['is_visible'], [true, false, 0, 1, '0', '1'], true))) {
                $errors['sections.' . $index] = lang('CvBuilder.invalid_sections');
                continue;
            }
            $seen[$section['section_key']] = true;
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saveProfileStep(string $cvId, array $payload): void
    {
        $profileModel = new CvProfile();
        $profile      = $payload['profile'];
        $photoPath    = $profileModel->where('cv_id', $cvId)->first()?->photo_path;
        $profileData  = [
            'cv_id'     => $cvId,
            'full_name' => trim((string) ($profile['full_name'] ?? '')),
            'headline'  => $this->nullableText($profile['headline'] ?? null),
            'email'     => $this->nullableText($profile['email'] ?? null),
            'phone'     => $this->nullableText($profile['phone'] ?? null),
            'city'      => $this->nullableText($profile['city'] ?? null),
            'country'   => $this->nullableText($profile['country'] ?? null),
            'photo_path'=> $photoPath,
        ];

        $this->replaceProfile($profileModel, $cvId, $profileData);
        $this->replaceRows($cvId, 'links', $payload['links'] ?? []);
        if (! $this->cvModel->update($cvId, ['title' => trim($payload['title'])])) {
            throw new DatabaseException('Judul CV tidak dapat disimpan.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saveSummaryStep(string $cvId, array $payload): void
    {
        $model   = new CvProfile();
        $profile = $model->where('cv_id', $cvId)->first();
        if ($profile === null) {
            throw new CvValidationException(['profile.full_name' => lang('CvBuilder.name_before_summary')]);
        }

        if (! $model->update($profile->id, ['summary' => $payload['summary']])) {
            throw new DatabaseException('Ringkasan CV tidak dapat disimpan.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saveAdditionalStep(string $cvId, array $payload): void
    {
        foreach (['projects', 'certifications', 'organizations', 'awards', 'languages', 'custom_sections'] as $collection) {
            $this->replaceRows($cvId, $collection, $payload[$collection] ?? []);
        }

        $settings = new CvSectionSetting();
        $settings->where('cv_id', $cvId)->delete();
        foreach (($payload['sections'] ?? []) as $sortOrder => $section) {
            if ($settings->insert([
                'cv_id'       => $cvId,
                'section_key' => $section['section_key'],
                'is_visible'  => filter_var($section['is_visible'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order'  => $sortOrder,
            ]) === false) {
                throw new DatabaseException('Pengaturan bagian CV tidak dapat disimpan.');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function validateTheme(array $payload): array
    {
        $themeKey = $payload['theme_key'] ?? null;

        return is_string($themeKey) && $this->themeRegistry->has($themeKey)
            ? []
            : ['theme_key' => lang('Themes.invalid_theme')];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saveThemeStep(string $cvId, array $payload): void
    {
        $theme = $this->themeRegistry->get($payload['theme_key']);
        $cv = $this->findById($cvId);
        $accentColor = $cv?->accent_color;
        if (! is_string($accentColor) || ! in_array(strtolower($accentColor), array_map('strtolower', $theme['accent_palette']), true)) {
            $accentColor = $theme['accent_palette'][0];
        }

        if (! $this->cvModel->update($cvId, [
            'theme_key' => $payload['theme_key'],
            'accent_color' => strtoupper($accentColor),
        ])) {
            throw new DatabaseException('Tema CV tidak dapat disimpan.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function validateDocumentSettings(string $cvId, array $payload): array
    {
        $errors = [];
        if (! in_array($payload['paper_size'] ?? null, ['a4', 'letter'], true)) {
            $errors['paper_size'] = lang('CvBuilder.invalid_paper_size');
        }
        if (! in_array($payload['font_scale'] ?? null, ['small', 'medium', 'large'], true)) {
            $errors['font_scale'] = lang('CvBuilder.invalid_font_scale');
        }

        $accentColor = $payload['accent_color'] ?? null;
        if (! is_string($accentColor)) {
            $errors['accent_color'] = lang('CvBuilder.invalid_accent_color');

            return $errors;
        }

        $cv = $this->findById($cvId);
        if ($cv === null || ! in_array(strtolower($accentColor), array_map('strtolower', $this->themeRegistry->get($cv->theme_key)['accent_palette']), true)) {
            $errors['accent_color'] = lang('CvBuilder.invalid_accent_color');
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saveDocumentSettings(string $cvId, array $payload): void
    {
        if (! $this->cvModel->update($cvId, [
            'paper_size'   => $payload['paper_size'],
            'font_scale'   => $payload['font_scale'],
            'accent_color' => strtoupper($payload['accent_color']),
        ])) {
            throw new DatabaseException('Pengaturan dokumen CV tidak dapat disimpan.');
        }
    }

    private function findById(string $cvId): ?CvEntity
    {
        $cv = $this->cvModel->find($cvId);

        return $cv instanceof CvEntity ? $cv : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function replaceProfile(CvProfile $model, string $cvId, array $data): void
    {
        $model->where('cv_id', $cvId)->delete();

        if ($model->insert($data) === false) {
            throw new DatabaseException('Data pribadi CV tidak dapat disimpan.');
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function replaceRows(string $cvId, string $collection, array $rows): void
    {
        $definition = self::COLLECTIONS[$collection];
        $model      = new $definition['class']();
        $model->where('cv_id', $cvId)->delete();

        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $data = ['cv_id' => $cvId, 'sort_order' => $index];
            foreach ($definition['fields'] as $field => $rules) {
                $value = $row[$field] ?? null;
                if ($field === 'is_current') {
                    $data[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($field === 'end_date' && ! empty($row['is_current'])) {
                    $data[$field] = null;
                } elseif (in_array($rules['type'], ['text', 'url', 'date'], true)) {
                    $data[$field] = $this->nullableText($value);
                } elseif (in_array($rules['type'], ['lines'], true)) {
                    $data[$field] = array_values(array_filter(array_map(
                        static fn (mixed $item): string => trim((string) $item),
                        is_array($value) ? $value : [],
                    ), static fn (string $item): bool => $item !== ''));
                } else {
                    $data[$field] = $value;
                }
            }

            if ($model->insert($data) === false) {
                throw new DatabaseException('Data CV tidak dapat disimpan.');
            }
        }
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $data
     */
    private function validateText(array &$errors, array $data, string $field, string $path, bool $required, int $maximum): void
    {
        $value = $data[$field] ?? '';
        if (! is_string($value) || ($required && trim($value) === '')) {
            $errors[$path] = lang('CvBuilder.field_required');

            return;
        }
        if (mb_strlen($value) > $maximum) {
            $errors[$path] = lang('CvBuilder.field_too_long', [$maximum]);
        }
    }

    private function isHttpUrl(string $value): bool
    {
        $scheme = parse_url($value, PHP_URL_SCHEME);

        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    private function isValidDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    /**
     * @param list<mixed> $values
     */
    private function validLines(array $values, int $maximum): bool
    {
        foreach ($values as $value) {
            if (! is_string($value) || mb_strlen($value) > $maximum) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (is_array($value) && array_filter($value, static fn (mixed $item): bool => trim((string) $item) !== '') !== []) {
                return false;
            }
            if ($value === false || $value === 0 || $value === '0') {
                continue;
            }
            if (! is_array($value) && ! $this->isEmptyValue($value)) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
