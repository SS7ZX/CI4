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

    public function testCreatingCvPersistsApplicationGeneratedUuid(): void
    {
        $userId = $this->createUser('builder-create@example.test', 'builder-create');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        try {
            $response = $this->withHeaders([
                'X-CSRF-TOKEN' => csrf_hash(),
                'Accept'       => 'text/html',
            ])->post('/dashboard/cv', ['title' => 'CV baru']);

            $response->assertRedirect();

            $cv = $this->db->table('cvs')->where('user_id', $userId)->get()->getRowArray();
            $this->assertIsArray($cv);
            $this->assertMatchesRegularExpression(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
                $cv['id'],
            );
            $this->assertStringContainsString($cv['id'], $response->getRedirectUrl());
        } finally {
            auth()->logout();
        }
    }

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
        $editor->assertSee('Langkah 6 dari 8');
        $editor->assertSee('aria-live="polite"');
        $this->assertMatchesRegularExpression(
            '/<section\s+id="builder-step-6"\s+x-bind:hidden="step !== 6"/',
            $editor->getBody(),
        );
        $this->assertMatchesRegularExpression(
            '/<section\s+id="builder-step-1"\s+hidden\s+x-bind:hidden="step !== 1"/',
            $editor->getBody(),
        );
        $editor->assertSee('id="profile-full_name"');

        $this->withSession($_SESSION);
        $personalEditor = $this->get('/dashboard/cv/' . $cvId . '/step/1');
        $personalEditor->assertOK();
        $this->assertMatchesRegularExpression(
            '/<section\s+id="builder-step-1"\s+x-bind:hidden="step !== 1"/',
            $personalEditor->getBody(),
        );
        $personalEditor->assertSee('id="profile-full_name"');

        $this->withSession($_SESSION);
        try {
            $response = $this->withBodyFormat('json')
                ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'Accept' => 'application/json'])
                ->post('/dashboard/cv/' . $cvId . '/autosave/1', [
                    'title'   => 'CV tersimpan lewat autosave',
                    'profile' => ['full_name' => 'Nama dari autosave'],
                    'links'   => [],
                ]);

            $response->assertOK();
            $payload = json_decode($response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('saved', $payload['status']);
            $this->assertSame('CV tersimpan lewat autosave', (new CvService())->findOwned($userId, $cvId)?->title);
            $this->assertSame(
                'Nama dari autosave',
                $this->db->table('cv_profiles')->where('cv_id', $cvId)->get()->getRowArray()['full_name'],
            );
        } finally {
            auth()->logout();
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
