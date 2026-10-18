<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

final class CreateJobMatchingPreferencesAndDictionaries extends Migration
{
    private bool $isPostgre;

    public function up(): void
    {
        $this->isPostgre = $this->db->DBDriver === 'Postgre';

        $this->forge->addField([
            'id'                       => $this->uuidField(),
            'cv_id'                    => $this->uuidField(),
            'target_titles'            => $this->jsonField(),
            'locations'                => $this->jsonField(),
            'remote_mode'              => $this->stringField(16, 'any'),
            'job_types'                => $this->jsonField(),
            'min_salary_idr'           => ['type' => 'INTEGER', 'null' => true],
            'excluded_keywords'        => $this->jsonField(),
            'experience_level_override' => $this->stringField(24, null, true),
            'search_enabled'           => ['type' => 'BOOLEAN', 'default' => false],
            'consent_at'               => $this->timestampField(true),
            'updated_at'               => $this->timestampField(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('cv_id');
        $this->forge->addForeignKey('cv_id', 'cvs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('job_preferences');

        $this->forge->addField([
            'id'        => $this->uuidField(),
            'canonical' => $this->stringField(160),
            'aliases'   => $this->jsonField(),
            'category'  => $this->stringField(64),
            'weight'    => ['type' => 'SMALLINT', 'default' => 1],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('canonical');
        $this->forge->addKey('category');
        $this->forge->createTable('skills_dictionary');

        $this->forge->addField([
            'id'             => $this->uuidField(),
            'canonical_title' => $this->stringField(160),
            'aliases'        => $this->jsonField(),
            'job_family'     => $this->stringField(64),
            'seniority_hint' => $this->stringField(24, null, true),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('canonical_title');
        $this->forge->addKey('job_family');
        $this->forge->createTable('title_dictionary');

        if ($this->isPostgre) {
            foreach (['job_preferences', 'skills_dictionary', 'title_dictionary'] as $table) {
                $this->db->query('ALTER TABLE public.' . $table . ' ENABLE ROW LEVEL SECURITY');
            }
        }
    }

    public function down(): void
    {
        foreach (['title_dictionary', 'skills_dictionary', 'job_preferences'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function uuidField(): array
    {
        if ($this->isPostgre) {
            return ['type' => 'UUID', 'default' => new RawSql('gen_random_uuid()')];
        }

        return ['type' => 'VARCHAR', 'constraint' => 36];
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
    private function stringField(int $length, string|null $default = null, bool $nullable = false): array
    {
        $field = [
            'type'       => 'VARCHAR',
            'constraint' => $length,
            'null'       => $nullable,
        ];
        if ($default !== null) {
            $field['default'] = $default;
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
}
