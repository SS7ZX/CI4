<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use CodeIgniter\Shield\Config\Auth;

final class CreateCvTables extends Migration
{
    private bool $isPostgre;

    public function up(): void
    {
        $this->isPostgre = $this->db->DBDriver === 'Postgre';

        $this->createCvTable();
        $this->createChildTable('cv_profiles', [
            'full_name'  => $this->stringField(160),
            'headline'   => $this->stringField(160, true),
            'email'      => $this->stringField(254, true),
            'phone'      => $this->stringField(40, true),
            'city'       => $this->stringField(120, true),
            'country'    => $this->stringField(120, true),
            'summary'    => $this->textField(true),
            'photo_path' => $this->stringField(500, true),
        ], true);
        $this->createChildTable('cv_links', [
            'label'      => $this->stringField(80),
            'url'        => $this->stringField(2048),
            'sort_order' => $this->integerField(0),
        ]);
        $this->createChildTable('cv_experiences', [
            'company'    => $this->stringField(160),
            'position'   => $this->stringField(160),
            'location'   => $this->stringField(160, true),
            'start_date' => $this->dateField(),
            'end_date'   => $this->dateField(true),
            'is_current' => $this->booleanField(false),
            'bullets'    => $this->jsonField(),
            'sort_order' => $this->integerField(0),
        ]);
        $this->createChildTable('cv_educations', [
            'institution'    => $this->stringField(200),
            'degree'         => $this->stringField(120),
            'field_of_study' => $this->stringField(160),
            'gpa'            => $this->stringField(20, true),
            'start_date'     => $this->dateField(),
            'end_date'       => $this->dateField(true),
            'description'    => $this->textField(true),
            'sort_order'     => $this->integerField(0),
        ]);
        $this->createChildTable('cv_skill_groups', [
            'name'       => $this->stringField(120),
            'items'      => $this->jsonField(),
            'sort_order' => $this->integerField(0),
        ]);
        $this->createChildTable('cv_projects', [
            'name'        => $this->stringField(160),
            'role'        => $this->stringField(120, true),
            'url'         => $this->stringField(2048, true),
            'start_date'  => $this->dateField(true),
            'end_date'    => $this->dateField(true),
            'bullets'     => $this->jsonField(),
            'tech_stack'  => $this->jsonField(),
            'sort_order'  => $this->integerField(0),
        ]);
        $this->createChildTable('cv_certifications', [
            'name'           => $this->stringField(180),
            'issuer'         => $this->stringField(180),
            'issued_at'      => $this->dateField(true),
            'expires_at'     => $this->dateField(true),
            'credential_url' => $this->stringField(2048, true),
            'sort_order'     => $this->integerField(0),
        ]);
        $this->createChildTable('cv_organizations', [
            'organization' => $this->stringField(180),
            'role'         => $this->stringField(120),
            'start_date'   => $this->dateField(),
            'end_date'     => $this->dateField(true),
            'bullets'      => $this->jsonField(),
            'sort_order'   => $this->integerField(0),
        ]);
        $this->createChildTable('cv_awards', [
            'title'       => $this->stringField(180),
            'issuer'      => $this->stringField(180, true),
            'awarded_at'  => $this->dateField(true),
            'description' => $this->textField(true),
            'sort_order'  => $this->integerField(0),
        ]);
        $this->createChildTable('cv_languages', [
            'name'       => $this->stringField(120),
            'level'      => $this->stringField(24),
            'sort_order' => $this->integerField(0),
        ]);
        $this->createChildTable('cv_custom_sections', [
            'title'      => $this->stringField(120),
            'items'      => $this->jsonField(),
            'sort_order' => $this->integerField(0),
        ]);
        $this->createChildTable('cv_section_settings', [
            'section_key' => $this->stringField(80),
            'is_visible'  => $this->booleanField(true),
            'sort_order'  => $this->integerField(0),
        ]);

        if ($this->isPostgre) {
            $this->enableRls();
        }
    }

    public function down(): void
    {
        $tables = [
            'cv_section_settings',
            'cv_custom_sections',
            'cv_languages',
            'cv_awards',
            'cv_organizations',
            'cv_certifications',
            'cv_projects',
            'cv_skill_groups',
            'cv_educations',
            'cv_experiences',
            'cv_links',
            'cv_profiles',
            'cvs',
        ];

        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }

        if ($this->db->DBDriver === 'Postgre') {
            $this->disableShieldRls();
        }
    }

    private function createCvTable(): void
    {
        $this->forge->addField([
            'id'           => $this->uuidField(),
            'user_id'      => $this->bigintField(),
            'title'        => $this->stringField(160),
            'theme_key'    => $this->stringField(80, false, 'ats-classic'),
            'locale'       => $this->stringField(12, false, 'id'),
            'paper_size'   => $this->stringField(8, false, 'a4'),
            'font_scale'   => $this->stringField(12, false, 'medium'),
            'accent_color' => $this->stringField(7, true),
            'settings'     => $this->jsonField(),
            'last_step'    => $this->integerField(1),
            'created_at'   => $this->timestampField(),
            'updated_at'   => $this->timestampField(),
            'deleted_at'   => $this->timestampField(true),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cvs');
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function createChildTable(string $table, array $fields, bool $uniqueCvId = false): void
    {
        $this->forge->addField([
            'id'         => $this->uuidField(),
            'cv_id'      => $this->uuidField(),
            ...$fields,
            'created_at' => $this->timestampField(),
            'updated_at' => $this->timestampField(),
        ]);
        $this->forge->addPrimaryKey('id');
        if ($uniqueCvId) {
            $this->forge->addUniqueKey('cv_id');
        } else {
            $this->forge->addKey('cv_id');
        }
        if (isset($fields['sort_order'])) {
            $this->forge->addKey('sort_order');
        }
        $this->forge->addForeignKey('cv_id', 'cvs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable($table);
    }

    /**
     * @return array<string, mixed>
     */
    private function uuidField(): array
    {
        $field = [
            'type'       => $this->isPostgre ? 'UUID' : 'VARCHAR',
        ];

        if ($this->isPostgre) {
            $field['default'] = new RawSql('gen_random_uuid()');
        } else {
            $field['constraint'] = 36;
        }

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function timestampField(bool $nullable = false): array
    {
        return [
            'type' => $this->isPostgre ? 'TIMESTAMPTZ' : 'DATETIME',
            'null' => $nullable,
            ...($nullable ? [] : ['default' => $this->isPostgre ? new RawSql('CURRENT_TIMESTAMP') : 'CURRENT_TIMESTAMP']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stringField(int $length, bool $nullable = false, ?string $default = null): array
    {
        return [
            'type'       => 'VARCHAR',
            'constraint' => $length,
            'null'       => $nullable,
            ...($default === null ? [] : ['default' => $default]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textField(bool $nullable = false): array
    {
        return ['type' => 'TEXT', 'null' => $nullable];
    }

    /**
     * @return array<string, mixed>
     */
    private function integerField(?int $default = null): array
    {
        return [
            'type' => 'INTEGER',
            ...($default === null ? [] : ['default' => $default]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bigintField(): array
    {
        return ['type' => 'BIGINT'];
    }

    /**
     * @return array<string, mixed>
     */
    private function booleanField(bool $default): array
    {
        return [
            'type'    => $this->isPostgre ? 'BOOLEAN' : 'INTEGER',
            'default' => $default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonField(): array
    {
        return [
            'type'    => $this->isPostgre ? 'JSONB' : 'TEXT',
            'default' => $this->isPostgre ? new RawSql("'[]'::jsonb") : '[]',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dateField(bool $nullable = false): array
    {
        return ['type' => 'DATE', 'null' => $nullable];
    }

    private function enableRls(): void
    {
        foreach ($this->getShieldTableNames() as $table) {
            $this->db->query('ALTER TABLE public.' . $this->db->escapeIdentifiers($table) . ' ENABLE ROW LEVEL SECURITY');
        }

        foreach ([
            'cvs',
            'cv_profiles',
            'cv_links',
            'cv_experiences',
            'cv_educations',
            'cv_skill_groups',
            'cv_projects',
            'cv_certifications',
            'cv_organizations',
            'cv_awards',
            'cv_languages',
            'cv_custom_sections',
            'cv_section_settings',
        ] as $table) {
            $this->db->query('ALTER TABLE public.' . $this->db->escapeIdentifiers($table) . ' ENABLE ROW LEVEL SECURITY');
        }
    }

    private function disableShieldRls(): void
    {
        foreach ($this->getShieldTableNames() as $table) {
            $this->db->query('ALTER TABLE public.' . $this->db->escapeIdentifiers($table) . ' DISABLE ROW LEVEL SECURITY');
        }
    }

    /**
     * @return list<string>
     */
    private function getShieldTableNames(): array
    {
        $auth = config(Auth::class);

        return array_values($auth->tables);
    }
}
