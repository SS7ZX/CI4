<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

final class CheckSupabase extends BaseCommand
{
    protected $group = 'Development';
    protected $name = 'db:check-supabase';
    protected $description = 'Memeriksa koneksi PostgreSQL Supabase pada environment development.';

    public function run(array $params)
    {
        if (ENVIRONMENT !== 'development') {
            CLI::error('Perintah ini hanya tersedia pada environment development.');

            return EXIT_ERROR;
        }

        if (! extension_loaded('pgsql')) {
            CLI::error('Ekstensi PHP pgsql belum aktif.');

            return EXIT_ERROR;
        }

        $databaseConfig = config(Database::class)->default;
        foreach (['hostname', 'database', 'username', 'password'] as $key) {
            if (! is_string($databaseConfig[$key] ?? null) || $databaseConfig[$key] === '') {
                CLI::error('Lengkapi pengaturan koneksi database di file .env.');

                return EXIT_ERROR;
            }
        }

        try {
            $result = Database::connect()
                ->query('SELECT 1 AS connected, current_database() AS database_name')
                ->getRowArray();
        } catch (Throwable $exception) {
            CLI::error('Koneksi PostgreSQL Supabase gagal: ' . $exception->getMessage());

            return EXIT_ERROR;
        }

        if ((int) ($result['connected'] ?? 0) !== 1) {
            CLI::error('Koneksi PostgreSQL Supabase gagal: query uji tidak menghasilkan respons yang diharapkan.');

            return EXIT_ERROR;
        }

        CLI::write('Koneksi PostgreSQL Supabase berhasil. Database: ' . $result['database_name'], 'green');

        return EXIT_SUCCESS;
    }
}
