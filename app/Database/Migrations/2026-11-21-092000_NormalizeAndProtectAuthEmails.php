<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class NormalizeAndProtectAuthEmails extends Migration
{
    public function up(): void
    {
        if ($this->db->getPlatform() !== 'Postgre') {
            return;
        }

        $this->db->query(
            "UPDATE auth_identities
             SET secret = lower(btrim(secret)), updated_at = now()
             WHERE type = 'email_password'",
        );

        $this->db->query(
            "CREATE UNIQUE INDEX IF NOT EXISTS auth_email_password_secret_unique
             ON auth_identities ((lower(btrim(secret))))
             WHERE type = 'email_password'",
        );
    }

    public function down(): void
    {
        if ($this->db->getPlatform() !== 'Postgre') {
            return;
        }

        $this->db->query('DROP INDEX IF EXISTS auth_email_password_secret_unique');
    }
}
