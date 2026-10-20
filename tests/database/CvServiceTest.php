<?php

declare(strict_types=1);

use App\Models\Cv;
use App\Models\CvExperience;
use App\Services\CvService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class CvServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testCvModelsDoNotRequestDatabaseGeneratedIds(): void
    {
        $model = new Cv();
        $property = new ReflectionProperty($model, 'useAutoIncrement');

        $this->assertFalse($property->getValue($model));
    }

    public function testCreateDuplicateAndSoftDeletePreserveOwnershipAndChildren(): void
    {
        $userId  = $this->createUser('owner@example.test', 'owner');
        $service = new CvService();
        $cvId    = $service->create($userId, 'CV Sumber');

        $experience = new CvExperience();
        $experienceId = $experience->insert([
            'cv_id'      => $cvId,
            'company'    => 'Perusahaan',
            'position'   => 'Analis',
            'start_date' => '2025-01-01',
            'bullets'    => ['Menyusun laporan'],
            'sort_order' => 0,
        ]);
        $this->assertIsString($experienceId);

        $duplicateId = $service->duplicate($userId, $cvId);
        $this->assertNotSame($cvId, $duplicateId);
        $this->assertSame('CV Sumber - salinan', $service->findOwned($userId, $duplicateId)?->title);
        $this->assertSame(
            1,
            $this->db->table('cv_experiences')->where('cv_id', $duplicateId)->countAllResults(),
        );

        $this->assertTrue($service->softDelete($userId, $cvId));
        $this->assertNull($service->findOwned($userId, $cvId));
    }

    public function testDuplicateAndDeleteReturnNotFoundForNonOwner(): void
    {
        $ownerId = $this->createUser('owner-2@example.test', 'owner-2');
        $otherId = $this->createUser('other@example.test', 'other');
        $cvId    = (new CvService())->create($ownerId, 'CV milik pemilik');
        $service = new CvService();

        try {
            $service->duplicate($otherId, $cvId);
            $this->fail('Duplikasi CV milik pengguna lain harus menghasilkan 404.');
        } catch (PageNotFoundException) {
            $this->assertNull($service->findOwned($otherId, $cvId));
        }

        try {
            $service->softDelete($otherId, $cvId);
            $this->fail('Penghapusan CV milik pengguna lain harus menghasilkan 404.');
        } catch (PageNotFoundException) {
            $this->assertNotNull($service->findOwned($ownerId, $cvId));
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
