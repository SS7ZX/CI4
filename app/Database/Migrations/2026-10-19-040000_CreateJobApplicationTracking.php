<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

final class CreateJobApplicationTracking extends Migration
{
    private bool $isPostgre;

    public function up(): void
    {
        $this->isPostgre = $this->db->DBDriver === 'Postgre';

        $this->forge->addField([
            'id' => $this->uuidField(),
            'cv_id' => $this->uuidField(),
            'match_id' => $this->uuidField(true),
            'application_status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'not_applied'],
            'status_changed_at' => $this->timestampField(),
            'notes' => ['type' => 'TEXT', 'null' => true],
            'saved_expired' => ['type' => $this->isPostgre ? 'BOOLEAN' : 'INTEGER', 'default' => false],
            'created_at' => $this->timestampField(),
            'updated_at' => $this->timestampField(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('match_id');
        $this->forge->addKey('cv_id');
        $this->forge->addKey('application_status');
        $this->forge->addForeignKey('cv_id', 'cvs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('match_id', 'job_matches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('job_application_trackers');

        $this->forge->addField([
            'id' => $this->uuidField(),
            'tracker_id' => $this->uuidField(),
            'application_status' => ['type' => 'VARCHAR', 'constraint' => 24],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'changed_at' => $this->timestampField(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('tracker_id');
        $this->forge->addKey('changed_at');
        $this->forge->addForeignKey('tracker_id', 'job_application_trackers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('job_application_events');

        if ($this->isPostgre) {
            foreach (['job_application_trackers', 'job_application_events'] as $table) {
                $this->db->query(
                    'ALTER TABLE public.' . $this->db->prefixTable($table) . ' ENABLE ROW LEVEL SECURITY',
                );
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('job_application_events', true);
        $this->forge->dropTable('job_application_trackers', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function uuidField(bool $nullable = false): array
    {
        return $this->isPostgre
            ? ['type' => 'UUID', 'default' => $nullable ? null : new RawSql('gen_random_uuid()'), 'null' => $nullable]
            : ['type' => 'VARCHAR', 'constraint' => 36, 'null' => $nullable];
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
