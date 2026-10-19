<?php

declare(strict_types=1);

use App\Database\Seeds\JobDictionarySeeder;
use App\Services\CvService;
use App\Services\JobConsentRequiredException;
use App\Services\JobPreferenceService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class JobWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testConsentIsExplicitAndPreferencesAreDerivedThenEditable(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [$userId, $cvId] = $this->createUserAndCv('jobs@example.test', 'jobsuser');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $consentPage = $this->get('/dashboard/cv/' . $cvId . '/jobs');
        $consentPage->assertOK();
        $consentPage->assertSee('name="consent" value="1" required');
        $this->assertDoesNotMatchRegularExpression('/name="consent"[^>]*checked/', $consentPage->getBody());

        $preferencesWithoutConsent = $this->post('/dashboard/cv/' . $cvId . '/jobs/preferences', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs'),
            'target_titles' => ['Staf Administrasi'],
            'locations' => ['Bandung'],
            'remote_mode' => 'any',
            'job_types' => ['full_time'],
        ]);
        $preferencesWithoutConsent->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs');
        $this->assertSame(0, $this->db->table('job_preferences')->where('cv_id', $cvId)->countAllResults());

        $consentResponse = $this->post('/dashboard/cv/' . $cvId . '/jobs/consent', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs'),
            'consent' => '1',
        ]);
        $consentResponse->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/preferences');
        $stored = $this->db->table('job_preferences')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertSame(1, (int) $stored['search_enabled']);
        $this->assertNotEmpty($stored['consent_at']);
        $this->assertContains('Staf Administrasi', json_decode((string) $stored['target_titles'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertContains('Bandung', json_decode((string) $stored['locations'], true, 512, JSON_THROW_ON_ERROR));

        $preferencesPage = $this->get('/dashboard/cv/' . $cvId . '/jobs/preferences');
        $preferencesPage->assertOK();
        $preferencesPage->assertSee('Microsoft Excel');

        $unsafeTarget = $this->post('/dashboard/cv/' . $cvId . '/jobs/preferences', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/preferences'),
            'target_titles' => ['rina@example.test'],
            'locations' => ['Jl. Sudirman No. 10, Bandung'],
            'remote_mode' => 'any',
            'job_types' => ['full_time'],
        ]);
        $unsafeTarget->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/preferences');
        $unchanged = $this->db->table('job_preferences')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertSame(
            ['Staf Administrasi'],
            json_decode((string) $unchanged['target_titles'], true, 512, JSON_THROW_ON_ERROR),
        );

        $personalLocation = $this->post('/dashboard/cv/' . $cvId . '/jobs/preferences', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/preferences'),
            'target_titles' => ['Staf Administrasi'],
            'locations' => ['Nama Perusahaan Rahasia'],
            'remote_mode' => 'any',
            'job_types' => ['full_time'],
        ]);
        $personalLocation->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/preferences');
        $this->assertSame(
            ['Bandung'],
            json_decode((string) $this->db->table('job_preferences')->where('cv_id', $cvId)->get()->getRowArray()['locations'], true, 512, JSON_THROW_ON_ERROR),
        );

        $saved = $this->post('/dashboard/cv/' . $cvId . '/jobs/preferences', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/preferences'),
            'target_titles' => ['Staf Administrasi', 'Asisten Administrasi'],
            'locations' => ['Bandung'],
            'remote_mode' => 'hybrid',
            'job_types' => ['full_time', 'contract'],
            'min_salary_idr' => '8000000',
            'excluded_keywords' => ['shift malam'],
            'experience_level_override' => 'junior',
        ]);
        $saved->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/preferences');
        $stored = $this->db->table('job_preferences')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertSame('hybrid', $stored['remote_mode']);
        $this->assertSame(8000000, (int) $stored['min_salary_idr']);
        $this->assertSame(
            ['Staf Administrasi', 'Asisten Administrasi'],
            json_decode((string) $stored['target_titles'], true, 512, JSON_THROW_ON_ERROR),
        );

        $now = date('Y-m-d H:i:s');
        $postingId = $this->uuid();
        $matchId = $this->uuid();
        $this->db->table('job_postings')->insert([
            'id' => $postingId,
            'provider' => 'fixture',
            'dedupe_hash' => hash('sha256', $postingId),
            'title' => 'Staf Administrasi',
            'snippet' => 'Mengelola Microsoft Excel dan dokumen.',
            'link' => 'https://example.test/jobs/scored',
            'fetched_at' => $now,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'raw' => '{}',
        ]);
        $this->db->table('job_matches')->insert([
            'id' => $matchId,
            'cv_id' => $cvId,
            'job_posting_id' => $postingId,
            'matched_at' => $now,
            'score' => 88,
            'label' => 'very_good',
            'confidence' => 'tinggi',
            'breakdown' => json_encode([
                'title' => ['score' => 100, 'weight' => 30, 'normalized_weight' => 50, 'reason' => 'Judul sesuai.'],
                'skill' => ['score' => 76, 'weight' => 35, 'normalized_weight' => 50, 'reason' => 'Keahlian cocok.'],
            ], JSON_THROW_ON_ERROR),
            'matched_skills' => json_encode(['Microsoft Excel'], JSON_THROW_ON_ERROR),
            'missing_skills' => json_encode(['Pengarsipan', 'SQL'], JSON_THROW_ON_ERROR),
            'reasons' => json_encode(['Judul sesuai.', 'Keahlian cocok.'], JSON_THROW_ON_ERROR),
            'status' => 'new',
            'status_changed_at' => $now,
            'computed_at' => $now,
            'excluded_keyword_match' => false,
            'is_hidden_by_score' => false,
        ]);
        $this->db->table('job_postings')->where('id', $postingId)->update([
            'title' => '<script>alert(1)</script>',
            'company' => '<img src=x onerror=alert(1)>',
            'location' => 'Bandung',
            'job_type' => 'full_time',
            'is_remote' => true,
            'snippet' => '<svg onload=alert(1)>cuplikan</svg>',
            'source_name' => '<b>Sumber</b>',
        ]);
        $resultsPage = $this->get(
            '/dashboard/cv/' . $cvId . '/jobs/results?location=Bandung&job_type=full_time&remote=remote&min_score=80&sort=recent',
        );
        $resultsPage->assertOK();
        $resultsPage->assertSee('88/100');
        $resultsPage->assertSee('Sangat cocok');
        $resultsPage->assertSee('1 hasil ditemukan.');
        $this->assertStringNotContainsString('<script>', $resultsPage->getBody());
        $this->assertStringNotContainsString('<img', $resultsPage->getBody());

        $detailPage = $this->get('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $detailPage->assertOK();
        $detailPage->assertSee('Bobot awal 30, bobot yang dinormalisasi 50 persen.');
        $detailPage->assertSee('Pengarsipan');
        $detailPage->assertSee('SQL: belum ditemukan di CV');
        $detailPage->assertSee('Saran ini bukan ajakan untuk mengklaim kemampuan.');
        $detailPage->assertSee('Buka lowongan asli');
        $this->assertStringContainsString('Sumber lowongan: &lt;b&gt;Sumber&lt;/b&gt;.', $detailPage->getBody());
        $this->assertStringNotContainsString('<script>', $detailPage->getBody());
        $this->assertStringNotContainsString('<svg', $detailPage->getBody());
        $applicationUpdate = $this->post('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId . '/application', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId),
            'application_status' => 'applied',
            'application_notes' => 'Lamaran dikirim melalui situs perusahaan.',
        ]);
        $applicationUpdate->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $tracking = $this->db->table('job_application_trackers')->where('match_id', $matchId)->get()->getRowArray();
        $this->assertSame('applied', $tracking['application_status']);
        $this->assertSame('Lamaran dikirim melalui situs perusahaan.', $tracking['notes']);
        $this->assertSame(1, $this->db->table('job_application_events')->where('tracker_id', $tracking['id'])->countAllResults());
        $applicationDetail = $this->get('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $applicationDetail->assertSee('Sudah melamar');
        $applicationDetail->assertSee('Lamaran dikirim melalui situs perusahaan.');
        $trackerPage = $this->get('/dashboard/cv/' . $cvId . '/jobs/applications');
        $trackerPage->assertOK();
        $trackerPage->assertSee('Sudah melamar');
        $this->db->table('job_postings')->where('id', $postingId)->update(['link' => 'javascript:alert(1)']);
        $unsafeLinkPage = $this->get('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $unsafeLinkPage->assertSee('Tautan asli tidak tersedia.');
        $this->assertStringNotContainsString('javascript:', $unsafeLinkPage->getBody());
        $filteredPage = $this->get('/dashboard/cv/' . $cvId . '/jobs/results?job_type=part_time');
        $filteredPage->assertOK();
        $filteredPage->assertSee('Tidak ada hasil yang sesuai filter ini.');

        $saved = $this->post('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId . '/save', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId),
        ]);
        $saved->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $this->assertSame('saved', $this->db->table('job_matches')->where('id', $matchId)->get()->getRowArray()['status']);
        $unsaved = $this->post('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId . '/save', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId),
        ]);
        $unsaved->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $this->assertSame('new', $this->db->table('job_matches')->where('id', $matchId)->get()->getRowArray()['status']);
        $this->post('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId . '/save', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId),
        ])->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $hidden = $this->post('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId . '/hide', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId),
        ]);
        $hidden->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $this->assertSame('hidden', $this->db->table('job_matches')->where('id', $matchId)->get()->getRowArray()['status']);
        $activePage = $this->get('/dashboard/cv/' . $cvId . '/jobs/results');
        $activePage->assertSee('0 hasil ditemukan.');
        $hiddenPage = $this->get('/dashboard/cv/' . $cvId . '/jobs/results?status=hidden');
        $hiddenPage->assertSee('1 hasil ditemukan.');
        $restored = $this->post('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId . '/restore', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId),
        ]);
        $restored->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/match/' . $matchId);
        $this->assertSame('new', $this->db->table('job_matches')->where('id', $matchId)->get()->getRowArray()['status']);

        [, $otherCvId] = $this->createUserAndCv('other-jobs@example.test', 'otherjobs');
        $now = date('Y-m-d H:i:s');
        $this->db->table('job_matches')->insertBatch([
            [
                'id' => $this->uuid(),
                'cv_id' => $otherCvId,
                'job_posting_id' => $postingId,
                'matched_at' => $now,
                'score' => 88,
                'label' => 'very_good',
                'confidence' => 'tinggi',
                'breakdown' => '{}',
                'matched_skills' => '[]',
                'missing_skills' => '[]',
                'reasons' => '[]',
                'status' => 'new',
                'status_changed_at' => $now,
                'computed_at' => $now,
            ],
        ]);

        $revokeUrl = '/dashboard/cv/' . $cvId . '/jobs/consent/revoke';
        $revoked = $this->post($revokeUrl, [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/preferences'),
        ]);
        $revoked->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs');
        $stored = $this->db->table('job_preferences')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertSame(0, (int) $stored['search_enabled']);
        $this->assertNull($stored['consent_at']);
        $this->assertSame(0, $this->db->table('job_matches')->where('cv_id', $cvId)->countAllResults());
        $this->assertSame(0, $this->db->table('job_application_trackers')->where('cv_id', $cvId)->countAllResults());
        $this->assertSame(
            1,
            $this->db->table('job_matches')->where('cv_id', $otherCvId)->countAllResults(),
        );
        $this->get('/dashboard/cv/' . $cvId . '/jobs')->assertSee('name="consent" value="1" required');

        $this->post('/dashboard/cv/' . $cvId . '/jobs/consent', [
            'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs'),
            'consent' => '1',
        ])->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs/preferences');
        $csrfToken = $this->csrfTokenFrom('/dashboard/cv/' . $cvId . '/jobs/preferences');
        $deleted = $this->withHeaders(['X-CSRF-TOKEN' => $csrfToken])
            ->delete('/dashboard/cv/' . $cvId . '/jobs/consent');
        $deleted->assertRedirectTo('/dashboard/cv/' . $cvId . '/jobs');
        $stored = $this->db->table('job_preferences')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertSame(0, (int) $stored['search_enabled']);
        $this->db->table('cvs')->delete(['id' => $cvId]);
        $this->assertSame(0, $this->db->table('job_preferences')->where('cv_id', $cvId)->countAllResults());

        auth()->logout();
    }

    public function testAnotherUserCannotSeeOrChangeCvJobPreferences(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [, $cvId] = $this->createUserAndCv('owner@example.test', 'ownerjobs');
        [$otherUserId] = $this->createUserAndCv('other@example.test', 'otherjobs');
        auth()->loginById($otherUserId);
        $this->withSession($_SESSION);

        try {
            $this->get('/dashboard/cv/' . $cvId . '/jobs');
            $this->fail('Pengguna lain harus menerima 404 saat membuka preferensi CV.');
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        }

        auth()->logout();
    }

    public function testMatchIdFromAnotherCvReturnsNotFound(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [$ownerId, $ownerCvId] = $this->createUserAndCv('match-owner@example.test', 'matchowner');
        [$otherUserId, $otherCvId] = $this->createUserAndCv('match-other@example.test', 'matchother');
        $preferences = new JobPreferenceService();
        $preferences->grantConsent($ownerId, $ownerCvId);
        $preferences->grantConsent($otherUserId, $otherCvId);

        $now = date('Y-m-d H:i:s');
        $postingId = $this->uuid();
        $matchId = $this->uuid();
        $this->db->table('job_postings')->insert([
            'id' => $postingId,
            'provider' => 'fixture',
            'dedupe_hash' => hash('sha256', $postingId),
            'title' => 'Administrasi',
            'snippet' => 'Cuplikan deskripsi pekerjaan.',
            'link' => 'https://example.test/job',
            'fetched_at' => $now,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'raw' => '{}',
        ]);
        $this->db->table('job_matches')->insert([
            'id' => $matchId,
            'cv_id' => $ownerCvId,
            'job_posting_id' => $postingId,
            'matched_at' => $now,
            'score' => 65,
            'label' => 'good',
            'confidence' => 'sedang',
            'breakdown' => '{}',
            'matched_skills' => '[]',
            'missing_skills' => '[]',
            'reasons' => '[]',
            'status' => 'new',
            'status_changed_at' => $now,
            'computed_at' => $now,
        ]);

        auth()->loginById($otherUserId);
        $this->withSession($_SESSION);
        try {
            $this->get('/dashboard/cv/' . $otherCvId . '/jobs/match/' . $matchId);
            $this->fail('Match milik CV lain harus menghasilkan 404.');
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        }
        try {
            $this->post('/dashboard/cv/' . $otherCvId . '/jobs/match/' . $matchId . '/application', [
                'csrf_test_name' => $this->csrfTokenFrom('/dashboard/cv/' . $otherCvId . '/jobs/preferences'),
                'application_status' => 'applied',
                'application_notes' => 'Akses lintas CV',
            ]);
            $this->fail('Pengguna lain tidak boleh mengubah status lamaran match tersebut.');
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        }

        auth()->logout();
    }

    public function testInsightsSummarizeFixtureSkillsAndSalarySamples(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [$userId, $cvId] = $this->createUserAndCv('insights@example.test', 'insightsuser');
        (new JobPreferenceService())->grantConsent($userId, $cvId);

        $now = date('Y-m-d H:i:s');
        for ($index = 0; $index < 5; $index++) {
            $postingId = $this->uuid();
            $this->db->table('job_postings')->insert([
                'id' => $postingId,
                'provider' => 'fixture',
                'dedupe_hash' => hash('sha256', $postingId),
                'title' => 'Staf Administrasi',
                'snippet' => 'Lowongan administrasi untuk pengujian insight.',
                'link' => 'https://example.test/insight/' . $index,
                'salary_min_idr' => (5 + $index) * 1_000_000,
                'salary_max_idr' => (10 + $index) * 1_000_000,
                'fetched_at' => $now,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'raw' => '{}',
            ]);
            $this->db->table('job_matches')->insert([
                'id' => $this->uuid(),
                'cv_id' => $cvId,
                'job_posting_id' => $postingId,
                'matched_at' => $now,
                'score' => 80,
                'label' => 'good',
                'confidence' => 'sedang',
                'breakdown' => '{}',
                'matched_skills' => '[]',
                'missing_skills' => json_encode(['SQL', 'Pengarsipan'], JSON_THROW_ON_ERROR),
                'reasons' => '[]',
                'status' => 'new',
                'status_changed_at' => $now,
                'computed_at' => $now,
                'excluded_keyword_match' => false,
                'is_hidden_by_score' => false,
            ]);
        }

        auth()->loginById($userId);
        $this->withSession($_SESSION);
        $page = $this->get('/dashboard/cv/' . $cvId . '/jobs/insights');
        $page->assertOK();
        $page->assertSee('SQL');
        $page->assertSee('Diminta pada 5 lowongan');
        $page->assertDontSee('Pengarsipan');
        $page->assertSee('Sampel gaji tersedia: 5.');
        $page->assertSee('Rp 7.000.000');
        $page->assertSee('Rp 12.000.000');
        $page->assertSee('bukan jaminan atau estimasi tawaran');

        auth()->logout();
    }

    public function testSavedExpiredPostingKeepsOnlyEthicalTrackerMarkerAfterCleanup(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [$userId, $cvId] = $this->createUserAndCv('expired-saved@example.test', 'expiredsaved');
        (new JobPreferenceService())->grantConsent($userId, $cvId);
        $now = date('Y-m-d H:i:s');
        $postingId = $this->uuid();
        $matchId = $this->uuid();
        $this->db->table('job_postings')->insert([
            'id' => $postingId,
            'provider' => 'fixture',
            'dedupe_hash' => hash('sha256', $postingId),
            'title' => 'Lowongan lama yang sudah dihapus',
            'snippet' => 'Data ini harus dibersihkan.',
            'link' => 'https://example.test/expired',
            'fetched_at' => date('Y-m-d H:i:s', strtotime('-8 days')),
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'raw' => '{}',
        ]);
        $this->db->table('job_matches')->insert([
            'id' => $matchId,
            'cv_id' => $cvId,
            'job_posting_id' => $postingId,
            'matched_at' => $now,
            'score' => 75,
            'label' => 'very_good',
            'confidence' => 'rendah',
            'breakdown' => '{}',
            'matched_skills' => '[]',
            'missing_skills' => '[]',
            'reasons' => '[]',
            'status' => 'saved',
            'status_changed_at' => $now,
            'computed_at' => $now,
            'excluded_keyword_match' => false,
            'is_hidden_by_score' => false,
        ]);
        $this->assertTrue((new \App\Repositories\JobSearchRepository())->updateMatchStatus($cvId, $matchId, 'saved'));
        $this->assertSame(1, (new \App\Repositories\JobSearchRepository())->deleteExpired()['postings']);
        $tracker = $this->db->table('job_application_trackers')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertSame(1, (int) $tracker['saved_expired']);
        $this->assertNull($tracker['match_id']);
        $this->assertSame(0, $this->db->table('job_postings')->where('id', $postingId)->countAllResults());

        auth()->loginById($userId);
        $this->withSession($_SESSION);
        $page = $this->get('/dashboard/cv/' . $cvId . '/jobs/applications');
        $page->assertOK();
        $page->assertSee('Lowongan ini mungkin sudah ditutup.');
        $this->assertStringNotContainsString('Lowongan lama yang sudah dihapus', $page->getBody());

        auth()->logout();
    }

    public function testSearchGuardRequiresConsent(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [$userId, $cvId] = $this->createUserAndCv('guard@example.test', 'guardjobs');

        $this->expectException(JobConsentRequiredException::class);
        (new JobPreferenceService())->assertSearchAllowed($userId, $cvId);
    }

    public function testDashboardLinksEachCvToConsentWorkflow(): void
    {
        [$userId] = $this->createUserAndCv('dashboardjobs@example.test', 'dashboardjobs');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $dashboard = $this->get('/dashboard');
        $dashboard->assertOK();
        $dashboard->assertSee('Cari Lowongan');
        $dashboard->assertSee('/dashboard/cv/');

        auth()->logout();
    }

    /**
     * @return array{int, string}
     */
    private function createUserAndCv(string $email, string $username): array
    {
        $now = date('Y-m-d H:i:s');
        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => $username,
            'email' => $email,
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);
        $userId = (int) $users->getInsertID();
        $cvId = (new CvService())->create($userId, 'CV Contoh Administrasi');

        $this->db->table('cv_profiles')->insert([
            'id' => $this->uuid(),
            'cv_id' => $cvId,
            'full_name' => 'Nama Pribadi Contoh',
            'headline' => 'Staf Administrasi dan Keuangan',
            'email' => $email,
            'city' => 'Bandung',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->table('cv_skill_groups')->insert([
            'id' => $this->uuid(),
            'cv_id' => $cvId,
            'name' => 'Keahlian administrasi',
            'items' => json_encode(['Microsoft Excel', 'Pengarsipan'], JSON_THROW_ON_ERROR),
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->table('cv_experiences')->insert([
            'id' => $this->uuid(),
            'cv_id' => $cvId,
            'company' => 'Nama Perusahaan Rahasia',
            'position' => 'Staf Administrasi',
            'start_date' => '2022-01-01',
            'is_current' => true,
            'bullets' => json_encode(['Menyusun laporan Microsoft Excel.'], JSON_THROW_ON_ERROR),
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$userId, $cvId];
    }

    private function csrfTokenFrom(string $path): string
    {
        $page = $this->get($path);
        preg_match('/name=["\']csrf_test_name["\'][^>]*value=["\']([^"\']+)["\']/', $page->getBody(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->withSession($_SESSION);

        return $matches[1];
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
