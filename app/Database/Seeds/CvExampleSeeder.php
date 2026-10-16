<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;

final class CvExampleSeeder extends Seeder
{
    private const SAMPLE_EMAIL = 'cv-contoh@example.test';
    private const SAMPLE_TITLE = 'CV Contoh - Administrasi dan Keuangan';

    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('Seeder CV contoh hanya boleh dijalankan di luar production.');
        }

        $userModel = new UserModel();
        $user = $userModel->findByCredentials(['email' => self::SAMPLE_EMAIL]);

        if ($user === null) {
            $user = $userModel->createNewUser([
                'username' => 'cv-contoh',
                'email'    => self::SAMPLE_EMAIL,
                'password' => bin2hex(random_bytes(24)),
                'active'   => true,
            ]);
            $userModel->save($user);
            $user = $userModel->findByCredentials(['email' => self::SAMPLE_EMAIL]);
        }

        if ($user === null || $user->id === null) {
            throw new RuntimeException('Akun Shield untuk CV contoh tidak dapat dibuat.');
        }

        $existingCv = $this->db->table('cvs')
            ->where('user_id', $user->id)
            ->where('title', self::SAMPLE_TITLE)
            ->get()
            ->getRowArray();

        if ($existingCv !== null) {
            return;
        }

        $cvId = $this->uuid();
        $this->db->table('cvs')->insert([
            'id'           => $cvId,
            'user_id'      => $user->id,
            'title'        => self::SAMPLE_TITLE,
            'theme_key'    => 'ats-classic',
            'locale'       => 'id',
            'paper_size'   => 'a4',
            'font_scale'   => 'medium',
            'accent_color' => '#245B65',
            'settings'     => $this->json(['show_photo' => false]),
            'last_step'    => 8,
        ]);

        $this->insert('cv_profiles', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'full_name'  => 'Rina Contoh',
            'headline'   => 'Staf Administrasi dan Keuangan',
            'email'      => 'rina.contoh@example.test',
            'phone'      => '+62 812 0000 0000',
            'city'       => 'Bandung',
            'country'    => 'Indonesia',
            'summary'    => 'Profesional administrasi dengan pengalaman mengelola dokumen, laporan keuangan, dan koordinasi operasional.',
            'photo_path' => null,
        ]);
        $this->insert('cv_links', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'label'      => 'Portofolio',
            'url'        => 'https://example.test/rina-contoh',
            'sort_order' => 1,
        ]);
        $this->insert('cv_experiences', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'company'    => 'PT Contoh Sejahtera',
            'position'   => 'Staf Administrasi',
            'location'   => 'Bandung',
            'start_date' => '2022-01-01',
            'end_date'   => null,
            'is_current' => true,
            'bullets'    => $this->json([
                'Mengelola arsip digital dan dokumen operasional untuk tiga divisi.',
                'Menyiapkan laporan bulanan menggunakan Microsoft Excel.',
            ]),
            'sort_order' => 1,
        ]);
        $this->insert('cv_experiences', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'company'    => 'CV Contoh Mandiri',
            'position'   => 'Asisten Administrasi',
            'location'   => 'Bandung',
            'start_date' => '2020-06-01',
            'end_date'   => '2021-12-31',
            'is_current' => false,
            'bullets'    => $this->json([
                'Memeriksa kelengkapan dokumen transaksi dan surat masuk.',
                'Membantu rekonsiliasi data persediaan setiap bulan.',
            ]),
            'sort_order' => 2,
        ]);
        $this->insert('cv_educations', [
            'id'             => $this->uuid(),
            'cv_id'          => $cvId,
            'institution'    => 'Universitas Contoh Indonesia',
            'degree'         => 'Sarjana',
            'field_of_study' => 'Akuntansi',
            'gpa'            => '3.60',
            'start_date'     => '2016-08-01',
            'end_date'       => '2020-05-31',
            'description'    => 'Lulus dengan predikat sangat memuaskan.',
            'sort_order'     => 1,
        ]);
        $this->insert('cv_skill_groups', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'name'       => 'Administrasi dan Keuangan',
            'items'      => $this->json(['Microsoft Excel', 'Pengarsipan', 'Rekonsiliasi']),
            'sort_order' => 1,
        ]);
        $this->insert('cv_skill_groups', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'name'       => 'Perangkat Kerja',
            'items'      => $this->json(['Microsoft Word', 'Google Workspace']),
            'sort_order' => 2,
        ]);
        $this->insert('cv_projects', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'name'       => 'Digitalisasi Arsip Divisi',
            'role'       => 'Koordinator',
            'url'        => null,
            'start_date' => '2023-03-01',
            'end_date'   => '2023-07-31',
            'bullets'    => $this->json(['Menyusun struktur folder dan aturan penamaan dokumen.']),
            'tech_stack' => $this->json(['Google Drive', 'Microsoft Excel']),
            'sort_order' => 1,
        ]);
        $this->insert('cv_certifications', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'name'       => 'Microsoft Excel Tingkat Menengah',
            'issuer'     => 'Lembaga Pelatihan Contoh',
            'issued_at'  => '2022-11-15',
            'expires_at' => null,
            'credential_url' => null,
            'sort_order' => 1,
        ]);
        $this->insert('cv_organizations', [
            'id'           => $this->uuid(),
            'cv_id'        => $cvId,
            'organization' => 'Komunitas Relawan Contoh',
            'role'         => 'Bendahara',
            'start_date'   => '2018-02-01',
            'end_date'     => '2019-12-31',
            'bullets'      => $this->json(['Mencatat pemasukan dan pengeluaran kegiatan komunitas.']),
            'sort_order'   => 1,
        ]);
        $this->insert('cv_awards', [
            'id'          => $this->uuid(),
            'cv_id'       => $cvId,
            'title'       => 'Karyawan Teladan',
            'issuer'      => 'PT Contoh Sejahtera',
            'awarded_at'  => '2023-12-20',
            'description' => 'Penghargaan atas ketelitian dan kerapian pengelolaan arsip.',
            'sort_order'  => 1,
        ]);
        $this->insert('cv_languages', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'name'       => 'Bahasa Inggris',
            'level'      => 'conversational',
            'sort_order' => 1,
        ]);
        $this->insert('cv_custom_sections', [
            'id'         => $this->uuid(),
            'cv_id'      => $cvId,
            'title'      => 'Informasi Tambahan',
            'items'      => $this->json(['Bersedia bekerja secara hybrid.']),
            'sort_order' => 1,
        ]);

        foreach ([
            'profile',
            'links',
            'experience',
            'education',
            'skills',
            'projects',
            'certifications',
            'organizations',
            'awards',
            'languages',
            'custom',
        ] as $sortOrder => $sectionKey) {
            $this->insert('cv_section_settings', [
                'id'          => $this->uuid(),
                'cv_id'       => $cvId,
                'section_key' => $sectionKey,
                'is_visible'  => true,
                'sort_order'  => $sortOrder + 1,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): void
    {
        $this->db->table($table)->insert($row);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
