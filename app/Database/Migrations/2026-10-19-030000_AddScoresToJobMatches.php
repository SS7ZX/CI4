<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

final class AddScoresToJobMatches extends Migration
{
    private bool $isPostgre;

    public function up(): void
    {
        $this->isPostgre = $this->db->DBDriver === 'Postgre';
        $this->forge->addColumn('job_matches', [
            'score' => ['type' => 'SMALLINT', 'null' => true],
            'label' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'confidence' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'breakdown' => $this->jsonField(),
            'matched_skills' => $this->jsonField(),
            'missing_skills' => $this->jsonField(),
            'reasons' => $this->jsonField(),
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'new'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'status_changed_at' => $this->timestampField(),
            'computed_at' => $this->timestampField(true),
            'excluded_keyword_match' => ['type' => $this->isPostgre ? 'BOOLEAN' : 'INTEGER', 'default' => false],
            'is_hidden_by_score' => ['type' => $this->isPostgre ? 'BOOLEAN' : 'INTEGER', 'default' => false],
        ]);
        $this->forge->addKey('status');
        $this->forge->addKey('score');
    }

    public function down(): void
    {
        $this->forge->dropColumn('job_matches', [
            'score',
            'label',
            'confidence',
            'breakdown',
            'matched_skills',
            'missing_skills',
            'reasons',
            'status',
            'notes',
            'status_changed_at',
            'computed_at',
            'excluded_keyword_match',
            'is_hidden_by_score',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonField(): array
    {
        return [
            'type' => $this->isPostgre ? 'JSONB' : 'TEXT',
            'default' => $this->isPostgre ? new RawSql("'[]'::jsonb") : '[]',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timestampField(): array
    {
        return [
            'type' => $this->isPostgre ? 'TIMESTAMPTZ' : 'DATETIME',
            'default' => $this->isPostgre ? new RawSql('CURRENT_TIMESTAMP') : 'CURRENT_TIMESTAMP',
        ];
    }
}
