<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Email;
use Throwable;

final class TestEmail extends BaseCommand
{
    protected $group = 'Development';
    protected $name = 'email:test';
    protected $description = 'Mengirim satu email uji melalui konfigurasi SMTP.';

    public function run(array $params)
    {
        $recipient = trim((string) ($params[0] ?? ''));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            CLI::error('Gunakan: php spark email:test alamat@contoh.id');

            return EXIT_ERROR;
        }

        $config = config(Email::class);
        if (! $config->isSmtpConfigured()) {
            CLI::error('Konfigurasi SMTP belum lengkap di file .env.');

            return EXIT_ERROR;
        }

        try {
            $email = service('email');
            $email->setTo($recipient);
            $email->setSubject('Tes email CV Maker');
            $email->setMessage('Konfigurasi SMTP CV Maker berhasil digunakan.');

            if (! $email->send(false)) {
                CLI::error('SMTP menolak atau gagal mengirim email uji.');

                return EXIT_ERROR;
            }

            $email->clear(true);
            CLI::write('Email uji berhasil dikirim.', 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $exception) {
            CLI::error('Email uji gagal diproses: ' . $exception::class);

            return EXIT_ERROR;
        }
    }
}
