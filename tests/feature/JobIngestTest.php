<?php

declare(strict_types=1);

use App\Database\Seeds\JobDictionarySeeder;
use App\Libraries\Jobs\Dto\JobPostingDto;
use App\Libraries\Jobs\Dto\ProviderResult;
use App\Libraries\Jobs\JobIngestService;
use App\Libraries\Jobs\JobProviderException;
use App\Libraries\Jobs\JobProviderRegistry;
use App\Libraries\Jobs\JobSearchLimitException;
use App\Libraries\Jobs\Providers\FakeJobProvider;
use App\Repositories\JobSearchRepository;
use App\Services\JobConsentRequiredException;
use App\Services\CvService;
use App\Services\MatchService;
use App\Services\JobPreferenceService;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\JobMatching;

/**
 * @internal
 */
final class JobIngestTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testIngestDeduplicatesCachesAndNeverSendsCvPersonalData(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [, $firstCvId] = $this->createUserAndCv('first-ingest@example.test', 'firstingest');
        $preferenceService = new JobPreferenceService();
        $preferenceService->grantConsent($this->userIdForCv($firstCvId), $firstCvId);

        $posting = new JobPostingDto(
            'jooble-unique-1',
            'Staf Administrasi',
            'Perusahaan Lowongan',
            'Bandung',
            'Mengelola dokumen dan laporan.',
            'Rp 6.000.000 per bulan',
            'Jooble',
            'full-time',
            'https://example.test/jobs/1',
            null,
        );
        $provider = new FakeJobProvider([
            new ProviderResult([$posting, $posting], 2, 1),
        ], providerName: 'fake');
        $repository = new JobSearchRepository($this->db);
        $service = new JobIngestService(
            preferences: $preferenceService,
            providers: new JobProviderRegistry([$provider]),
            repository: $repository,
        );

        $firstResult = $service->refresh($this->userIdForCv($firstCvId), $firstCvId);

        $this->assertSame(1, $firstResult['inserted']);
        $this->assertSame(1, $firstResult['updated']);
        $this->assertCount(1, $provider->queries);
        $this->assertSame(1, $this->db->table('job_postings')->countAllResults());
        $queryText = $provider->queries[0]->keywords . ' ' . $provider->queries[0]->location;
        $this->assertStringNotContainsString('Nama Pribadi Contoh', $queryText);
        $this->assertStringNotContainsString('first-ingest@example.test', $queryText);
        $this->assertStringNotContainsString('Nama Perusahaan Rahasia', $queryText);
        $storedMatch = $this->db->table('job_matches')->where('cv_id', $firstCvId)->get()->getRowArray();
        $this->assertIsInt((int) $storedMatch['score']);
        $this->assertSame('very_good', $storedMatch['label']);
        $this->assertNotEmpty(json_decode((string) $storedMatch['breakdown'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertNotEmpty(json_decode((string) $storedMatch['reasons'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertNotEmpty($storedMatch['computed_at']);
        $this->db->table('job_matches')->where('id', $storedMatch['id'])->update([
            'status' => 'saved',
            'notes' => 'Cocok untuk ditinjau.',
        ]);
        $this->assertSame(1, (new MatchService())->recomputeForCv($this->userIdForCv($firstCvId), $firstCvId));
        $updatedMatch = $this->db->table('job_matches')->where('id', $storedMatch['id'])->get()->getRowArray();
        $this->assertSame('saved', $updatedMatch['status']);
        $this->assertSame('Cocok untuk ditinjau.', $updatedMatch['notes']);
        $this->assertNotEmpty($updatedMatch['computed_at']);

        [, $secondCvId] = $this->createUserAndCv('second-ingest@example.test', 'secondingest');
        $secondUserId = $this->userIdForCv($secondCvId);
        $preferenceService->grantConsent($secondUserId, $secondCvId);
        $secondResult = $service->refresh($secondUserId, $secondCvId);

        $this->assertSame(1, $secondResult['cached']);
        $this->assertCount(1, $provider->queries);
        $this->assertCount(1, $repository->matchesForCv($firstCvId));
        $this->assertCount(1, $repository->matchesForCv($secondCvId));
        $this->assertSame(1, $this->db->table('job_postings')->countAllResults());
    }

    public function testIngestRequiresConsentAndHonorsDailyLimitAndCooldown(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [$userId, $cvId] = $this->createUserAndCv('limits-ingest@example.test', 'limitsingest');
        $preferenceService = new JobPreferenceService();
        $provider = new FakeJobProvider();
        $repository = new JobSearchRepository($this->db);
        $service = new JobIngestService(
            preferences: $preferenceService,
            providers: new JobProviderRegistry([$provider]),
            repository: $repository,
        );

        try {
            $service->refresh($userId, $cvId);
            $this->fail('Pencarian harus ditolak sebelum persetujuan diberikan.');
        } catch (JobConsentRequiredException) {
            $this->assertSame([], $provider->queries);
        }

        $preferenceService->grantConsent($userId, $cvId);
        $limitedConfig = new JobMatching();
        $limitedConfig->dailySearchLimit = 0;
        $limitedService = new JobIngestService(
            preferences: $preferenceService,
            providers: new JobProviderRegistry([$provider]),
            repository: $repository,
            config: $limitedConfig,
        );
        try {
            $limitedService->refresh($userId, $cvId);
            $this->fail('Pencarian harus mematuhi kuota harian.');
        } catch (JobSearchLimitException) {
            $this->assertSame([], $provider->queries);
        }

        $repository->startRefresh($cvId);
        $cooldownConfig = new JobMatching();
        $cooldownConfig->cvRefreshCooldownSeconds = 300;
        $cooldownService = new JobIngestService(
            preferences: $preferenceService,
            providers: new JobProviderRegistry([$provider]),
            repository: $repository,
            config: $cooldownConfig,
        );
        try {
            $cooldownService->refresh($userId, $cvId);
            $this->fail('Pencarian harus menunggu selama cooldown.');
        } catch (JobSearchLimitException) {
            $this->assertSame([], $provider->queries);
        }
    }

    public function testProviderFailureReturnsWarningsWithoutAbortingIngest(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        [$userId, $cvId] = $this->createUserAndCv('failure-ingest@example.test', 'failureingest');
        $preferenceService = new JobPreferenceService();
        $preferenceService->grantConsent($userId, $cvId);
        $provider = new FakeJobProvider(
            failure: new JobProviderException('safe provider error'),
            providerName: 'fake',
        );
        $service = new JobIngestService(
            preferences: $preferenceService,
            providers: new JobProviderRegistry([$provider]),
            repository: new JobSearchRepository($this->db),
        );

        $result = $service->refresh($userId, $cvId);

        $this->assertSame([], $result['postings']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertCount(1, $provider->queries);
        $this->assertSame(1, $this->db->table('job_refresh_log')->where('cv_id', $cvId)->countAllResults());
    }

    private function createUserAndCv(string $email, string $username): array
    {
        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => $username,
            'email' => $email,
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);
        $userId = (int) $users->getInsertID();
        $cvId = (new CvService())->create($userId, 'CV Contoh Administrasi');
        $now = date('Y-m-d H:i:s');
        $this->db->table('cv_profiles')->insert([
            'id' => $this->uuid(),
            'cv_id' => $cvId,
            'full_name' => 'Nama Pribadi Contoh',
            'headline' => 'Staf Administrasi',
            'email' => $email,
            'city' => 'Bandung',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->table('cv_skill_groups')->insert([
            'id' => $this->uuid(),
            'cv_id' => $cvId,
            'name' => 'Keahlian',
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
            'bullets' => json_encode(['Menyiapkan laporan menggunakan Microsoft Excel.'], JSON_THROW_ON_ERROR),
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$userId, $cvId];
    }

    private function userIdForCv(string $cvId): int
    {
        return (int) $this->db->table('cvs')->select('user_id')->where('id', $cvId)->get()->getRow('user_id');
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
