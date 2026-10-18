<?php

declare(strict_types=1);

use App\Services\CvService;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class CvBuilderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testStepSixRendersAndJsonAutosavePersistsPersonalData(): void
    {
        $userId = $this->createUser('builder-feature@example.test', 'builder-feature');
        $cvId   = (new CvService())->create($userId, 'CV fitur');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $editor = $this->get('/dashboard/cv/' . $cvId . '/step/6');
        $editor->assertOK();
        $editor->assertSee('Tampilan dan urutan bagian CV');
        $editor->assertSee('Nama sertifikasi');
        $editor->assertSee('aria-live="polite"');

        $this->withSession($_SESSION);
        $response = $this->withBodyFormat('json')
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'Accept' => 'application/json'])
            ->post('/dashboard/cv/' . $cvId . '/autosave/1', [
                'title'   => 'CV tersimpan lewat autosave',
                'profile' => ['full_name' => 'Nama dari autosave'],
                'links'   => [],
            ]);

        $response->assertOK();
        $this->assertSame('saved', $response->getJSON()->status);
        $this->assertSame('CV tersimpan lewat autosave', (new CvService())->findOwned($userId, $cvId)?->title);
        $this->assertSame(
            'Nama dari autosave',
            $this->db->table('cv_profiles')->where('cv_id', $cvId)->get()->getRowArray()['full_name'],
        );
        auth()->logout();
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
