<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

final class CreateCvDownloadPayments extends Migration
{
    private bool $isPostgre;

    public function up(): void
    {
        $this->isPostgre = $this->db->DBDriver === 'Postgre';

        $this->forge->addField([
            'id'                  => $this->uuidField(),
            'cv_id'               => $this->uuidField(),
            'user_id'             => ['type' => 'BIGINT'],
            'amount_idr'          => ['type' => 'INTEGER'],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'proof_path'          => ['type' => 'VARCHAR', 'constraint' => 64],
            'reviewed_by'         => ['type' => 'BIGINT', 'null' => true],
            'review_note'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'proof_uploaded_at'   => $this->timestampField(),
            'reviewed_at'         => $this->timestampField(true),
            'created_at'          => $this->timestampField(),
            'updated_at'          => $this->timestampField(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('cv_id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('cv_id', 'cvs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cv_download_payments');

        if ($this->isPostgre) {
            $table = 'public.' . $this->db->prefixTable('cv_download_payments');
            $this->db->query('ALTER TABLE ' . $table . ' ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('cv_download_payments', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function uuidField(): array
    {
        return $this->isPostgre
            ? ['type' => 'UUID', 'default' => new RawSql('gen_random_uuid()')]
            : ['type' => 'VARCHAR', 'constraint' => 36];
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
