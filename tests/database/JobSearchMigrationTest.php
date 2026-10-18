<?php

declare(strict_types=1);

require_once APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'Migrations' . DIRECTORY_SEPARATOR
    . '2026-10-19-020000_CreateJobSearchTables.php';
require_once APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'Migrations' . DIRECTORY_SEPARATOR
    . '2026-10-19-030000_AddScoresToJobMatches.php';

use CodeIgniter\Database\Config as DatabaseConfig;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JobSearchMigrationTest extends TestCase
{
    public function testSearchTablesCanBeCreatedAndRolledBack(): void
    {
        $database = DatabaseConfig::connect([
            'DSN' => '',
            'hostname' => '127.0.0.1',
            'username' => '',
            'password' => '',
            'database' => ':memory:',
            'DBDriver' => 'SQLite3',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug' => true,
            'charset' => 'utf8',
            'foreignKeys' => true,
            'busyTimeout' => 1000,
        ], false);
        $forge = DatabaseConfig::forge($database);
        $forge->addField(['id' => ['type' => 'VARCHAR', 'constraint' => 36]]);
        $forge->addPrimaryKey('id');
        $forge->createTable('cvs');
        $migration = new \App\Database\Migrations\CreateJobSearchTables($forge);
        $scoringMigration = new \App\Database\Migrations\AddScoresToJobMatches($forge);

        try {
            $migration->up();
            $scoringMigration->up();
            foreach (['job_query_cache', 'job_postings', 'job_refresh_log', 'job_matches'] as $table) {
                $this->assertTrue($database->tableExists($table));
            }
            foreach ([
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
            ] as $column) {
                $this->assertContains($column, $database->getFieldNames('job_matches'));
            }

            $scoringMigration->down();
            $migration->down();

            foreach (['job_query_cache', 'job_postings', 'job_refresh_log', 'job_matches'] as $table) {
                $this->assertFalse($database->tableExists($table));
            }
        } finally {
            $database->close();
        }
    }
}
