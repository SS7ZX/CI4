<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

final class CreateJobSearchTables extends Migration
{
    private bool $isPostgre;

    public function up(): void
    {
        $this->isPostgre = $this->db->DBDriver === 'Postgre';

        if ($this->isPostgre) {
            $this->db->query('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        }

        $this->forge->addField([
            'id'           => $this->uuidField(),
            'query_hash'   => ['type' => 'VARCHAR', 'constraint' => 64],
            'provider'     => ['type' => 'VARCHAR', 'constraint' => 80],
            'keywords'     => ['type' => 'VARCHAR', 'constraint' => 500],
            'location'     => ['type' => 'VARCHAR', 'constraint' => 120],
            'page'         => ['type' => 'INTEGER', 'default' => 1],
            'total_count'  => ['type' => 'INTEGER', 'null' => true],
            'posting_ids'  => $this->jsonField(),
            'fetched_at'   => $this->timestampField(),
            'expires_at'   => $this->timestampField(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('query_hash');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('job_query_cache');

        $postingFields = [
            'id'              => $this->uuidField(),
            'provider'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'external_key'    => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'dedupe_hash'     => ['type' => 'VARCHAR', 'constraint' => 64],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 240],
            'company'         => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'location'        => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'is_remote'       => ['type' => $this->isPostgre ? 'BOOLEAN' : 'INTEGER', 'null' => true],
            'job_type'        => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'salary_text'     => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'salary_min_idr'  => ['type' => 'INTEGER', 'null' => true],
            'salary_max_idr'  => ['type' => 'INTEGER', 'null' => true],
            'snippet'         => ['type' => 'TEXT'],
            'source_name'     => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'link'            => ['type' => 'TEXT'],
            'posted_at'       => $this->timestampField(true),
            'fetched_at'      => $this->timestampField(),
            'expires_at'      => $this->timestampField(),
            'raw'             => $this->jsonField(),
        ];
        if (! $this->isPostgre) {
            $postingFields['search_vector'] = ['type' => 'TEXT', 'null' => true];
        }
        $this->forge->addField($postingFields);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('dedupe_hash');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('job_postings');

        $this->forge->addField([
            'id'             => $this->uuidField(),
            'cv_id'          => $this->uuidField(),
            'started_at'     => $this->timestampField(),
            'finished_at'    => $this->timestampField(true),
            'queries_run'    => ['type' => 'INTEGER', 'default' => 0],
            'postings_new'   => ['type' => 'INTEGER', 'default' => 0],
            'postings_updated' => ['type' => 'INTEGER', 'default' => 0],
            'error'          => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('cv_id');
        $this->forge->addKey('started_at');
        $this->forge->addForeignKey('cv_id', 'cvs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('job_refresh_log');

        $this->forge->addField([
            'id'               => $this->uuidField(),
            'cv_id'            => $this->uuidField(),
            'job_posting_id'   => $this->uuidField(),
            'matched_at'       => $this->timestampField(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['cv_id', 'job_posting_id']);
        $this->forge->addKey('cv_id');
        $this->forge->addKey('job_posting_id');
        $this->forge->addForeignKey('cv_id', 'cvs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('job_posting_id', 'job_postings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('job_matches');

        if ($this->isPostgre) {
            $postingsTable = 'public.' . $this->db->prefixTable('job_postings');
            $vectorIndex = $this->db->prefixTable('job_postings_search_vector_gin');
            $titleIndex = $this->db->prefixTable('job_postings_title_trgm_gin');
            $this->db->query(
                'ALTER TABLE ' . $postingsTable . ' ADD COLUMN search_vector TSVECTOR GENERATED ALWAYS AS ('
                . "setweight(to_tsvector('simple', coalesce(title, '')), 'A') || "
                . "setweight(to_tsvector('simple', coalesce(snippet, '')), 'B')) STORED",
            );
            $this->db->query('CREATE INDEX ' . $vectorIndex . ' ON ' . $postingsTable . ' USING GIN (search_vector)');
            $this->db->query('CREATE INDEX ' . $titleIndex . ' ON ' . $postingsTable . ' USING GIN (title gin_trgm_ops)');
            foreach (['job_query_cache', 'job_postings', 'job_refresh_log', 'job_matches'] as $table) {
                $this->db->query(
                    'ALTER TABLE public.' . $this->db->prefixTable($table) . ' ENABLE ROW LEVEL SECURITY',
                );
            }
        } else {
            $table = $this->db->prefixTable('job_postings');
            $this->db->query(
                'CREATE INDEX ' . $this->db->prefixTable('job_postings_search_vector_idx')
                . ' ON ' . $table . ' (search_vector)',
            );
            $this->db->query(
                'CREATE INDEX ' . $this->db->prefixTable('job_postings_title_idx')
                . ' ON ' . $table . ' (title)',
            );
        }
    }

    public function down(): void
    {
        foreach (['job_matches', 'job_refresh_log', 'job_postings', 'job_query_cache'] as $table) {
            $this->forge->dropTable($table, true);
        }
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
    private function timestampField(bool $nullable = false): array
    {
        return [
            'type' => $this->isPostgre ? 'TIMESTAMPTZ' : 'DATETIME',
            'null' => $nullable,
            ...($nullable ? [] : ['default' => $this->isPostgre ? new RawSql('CURRENT_TIMESTAMP') : 'CURRENT_TIMESTAMP']),
        ];
    }

}
