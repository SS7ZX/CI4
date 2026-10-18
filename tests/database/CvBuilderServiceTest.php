<?php

declare(strict_types=1);

use App\Services\CvBuilderService;
use App\Services\CvService;
use App\Services\CvValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class CvBuilderServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testProfileAndSummaryArePersistedWithTitleAndLastStep(): void
    {
        $userId  = $this->createUser('builder-profile@example.test', 'builder-profile');
        $cvId    = (new CvService())->create($userId, 'CV awal');
        $service = new CvBuilderService();

        $service->saveStep($userId, $cvId, 1, [
            'title'   => 'CV Analis Data',
            'profile' => [
                'full_name' => 'Nama Pengguna',
                'headline'  => 'Analis Data',
                'email'     => 'pengguna@example.test',
                'phone'     => '',
                'city'      => 'Bandung',
                'country'   => 'Indonesia',
            ],
            'links' => [
                ['label' => 'Portofolio', 'url' => 'https://example.test/portofolio'],
            ],
        ]);
        $service->saveStep($userId, $cvId, 2, ['summary' => 'Ringkasan profesional.']);

        $this->assertSame('CV Analis Data', $service->findOwned($userId, $cvId)?->title);
        $this->assertSame('Nama Pengguna', $this->db->table('cv_profiles')->where('cv_id', $cvId)->get()->getRowArray()['full_name']);
        $this->assertSame('Ringkasan profesional.', $this->db->table('cv_profiles')->where('cv_id', $cvId)->get()->getRowArray()['summary']);
        $this->assertSame(2, (int) $service->findOwned($userId, $cvId)?->last_step);
        $this->assertSame(1, $this->db->table('cv_links')->where('cv_id', $cvId)->countAllResults());
    }

    public function testSummaryCannotBeSavedBeforePersonalInformation(): void
    {
        $userId  = $this->createUser('builder-summary@example.test', 'builder-summary');
        $cvId    = (new CvService())->create($userId, 'CV');
        $service = new CvBuilderService();

        try {
            $service->saveStep($userId, $cvId, 2, ['summary' => 'Ringkasan']);
            $this->fail('Ringkasan sebelum data pribadi harus ditolak.');
        } catch (CvValidationException $exception) {
            $this->assertArrayHasKey('profile.full_name', $exception->errors());
        }

        $this->assertSame(0, $this->db->table('cv_profiles')->where('cv_id', $cvId)->countAllResults());
    }

    public function testProfileValidationReturnsFieldErrorsForTitleLengthAndUnsafeUrl(): void
    {
        $userId  = $this->createUser('builder-validation@example.test', 'builder-validation');
        $cvId    = (new CvService())->create($userId, 'CV');
        $service = new CvBuilderService();

        try {
            $service->saveStep($userId, $cvId, 1, [
                'title'   => str_repeat('x', 161),
                'profile' => ['full_name' => 'Nama'],
                'links'   => [['label' => 'Portofolio', 'url' => 'javascript:alert(1)']],
            ]);
            $this->fail('Judul terlalu panjang dan URL non-HTTP harus ditolak.');
        } catch (CvValidationException $exception) {
            $this->assertArrayHasKey('title', $exception->errors());
            $this->assertArrayHasKey('links.0.url', $exception->errors());
        }

        $this->assertSame('CV', $service->findOwned($userId, $cvId)?->title);
    }

    public function testExperienceDatesAreValidatedAndCurrentJobClearsEndDate(): void
    {
        $userId  = $this->createUser('builder-dates@example.test', 'builder-dates');
        $cvId    = (new CvService())->create($userId, 'CV');
        $service = new CvBuilderService();

        try {
            $service->saveStep($userId, $cvId, 3, [
                'experiences' => [[
                    'company'    => 'Perusahaan',
                    'position'   => 'Analis',
                    'start_date' => '2025-02-30',
                    'end_date'   => '2024-12-31',
                    'is_current' => false,
                    'bullets'    => [],
                ]],
            ]);
            $this->fail('Tanggal kalender tidak valid harus ditolak.');
        } catch (CvValidationException $exception) {
            $this->assertArrayHasKey('experiences.0.start_date', $exception->errors());
        }

        try {
            $service->saveStep($userId, $cvId, 3, [
                'experiences' => [[
                    'company'    => 'Perusahaan',
                    'position'   => 'Analis',
                    'start_date' => '2025-01-01',
                    'end_date'   => '2024-12-31',
                    'is_current' => false,
                    'bullets'    => [],
                ]],
            ]);
            $this->fail('Tanggal selesai sebelum tanggal mulai harus ditolak.');
        } catch (CvValidationException $exception) {
            $this->assertArrayHasKey('experiences.0.end_date', $exception->errors());
        }

        $service->saveStep($userId, $cvId, 3, [
            'experiences' => [[
                'company'    => 'Perusahaan',
                'position'   => 'Analis',
                'start_date' => '2025-01-01',
                'end_date'   => '2024-12-31',
                'is_current' => true,
                'bullets'    => [],
            ]],
        ]);

        $experience = $this->db->table('cv_experiences')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertNull($experience['end_date']);
    }

    public function testAdditionalCollectionsAndSectionVisibilityAndOrderPersist(): void
    {
        $userId  = $this->createUser('builder-additional@example.test', 'builder-additional');
        $cvId    = (new CvService())->create($userId, 'CV');
        $service = new CvBuilderService();

        $service->saveStep($userId, $cvId, 6, [
            'projects' => [[
                'name'       => 'Proyek contoh',
                'role'       => 'Kontributor',
                'url'        => 'https://example.test',
                'start_date' => '2025-01-01',
                'end_date'   => '2025-02-01',
                'bullets'    => ['Membuat fitur'],
                'tech_stack' => ['PHP'],
            ]],
            'certifications' => [],
            'organizations'  => [],
            'awards'         => [],
            'languages'      => [['name' => 'Bahasa Indonesia', 'level' => 'native']],
            'custom_sections'=> [['title' => 'Publikasi', 'items' => ['Artikel']]],
            'sections'       => [
                ['section_key' => 'summary', 'sort_order' => 9, 'is_visible' => false],
                ['section_key' => 'profile', 'sort_order' => 0, 'is_visible' => true],
            ],
        ]);

        $project = $this->db->table('cv_projects')->where('cv_id', $cvId)->get()->getRowArray();
        $this->assertSame('Proyek contoh', $project['name']);
        $this->assertSame('["Membuat fitur"]', $project['bullets']);
        $this->assertSame('["PHP"]', $project['tech_stack']);
        $this->assertSame(1, $this->db->table('cv_languages')->where('cv_id', $cvId)->countAllResults());
        $this->assertSame(1, $this->db->table('cv_custom_sections')->where('cv_id', $cvId)->countAllResults());
        $sectionSettings = $this->db->table('cv_section_settings')->where('cv_id', $cvId)->orderBy('sort_order')->get()->getResultArray();
        $this->assertSame(['summary', 'profile'], array_column($sectionSettings, 'section_key'));
        $this->assertSame(0, (int) $sectionSettings[0]['is_visible']);
    }

    public function testAnotherUserCannotLoadOrAutosaveCv(): void
    {
        $ownerId = $this->createUser('builder-owner@example.test', 'builder-owner');
        $otherId = $this->createUser('builder-other@example.test', 'builder-other');
        $cvId    = (new CvService())->create($ownerId, 'CV pemilik');
        $service = new CvBuilderService();

        try {
            $service->load($otherId, $cvId);
            $this->fail('Pengguna lain tidak boleh memuat CV.');
        } catch (PageNotFoundException) {
            $this->assertNull($service->findOwned($otherId, $cvId));
        }

        try {
            $service->saveStep($otherId, $cvId, 1, []);
            $this->fail('Pengguna lain tidak boleh mengubah CV.');
        } catch (PageNotFoundException) {
            $this->assertSame('CV pemilik', $service->findOwned($ownerId, $cvId)?->title);
        }
    }

    private function createUser(string $email, string $username): int
    {
        $users = model(UserModel::class);
        $user  = $users->createNewUser([
            'username' => $username,
            'email'    => $email,
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);

        return $users->getInsertID();
    }
}
