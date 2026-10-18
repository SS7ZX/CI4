<?php

declare(strict_types=1);

use App\Database\Seeds\JobDictionarySeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class JobDictionarySeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = ['CodeIgniter\\Shield', 'App'];

    public function testDictionarySeederProvidesBroadBilingualCoverageAndIsRepeatable(): void
    {
        $this->seeder->call(JobDictionarySeeder::class);
        $this->seeder->call(JobDictionarySeeder::class);

        $skills = $this->db->table('skills_dictionary')->get()->getResultArray();
        $titles = $this->db->table('title_dictionary')->get()->getResultArray();

        $this->assertGreaterThanOrEqual(200, count($skills));
        $this->assertGreaterThanOrEqual(120, count($titles));
        $skillCategories = array_unique(array_column($skills, 'category'));
        $this->assertGreaterThanOrEqual(10, count($skillCategories));

        $excel = $this->db->table('skills_dictionary')->where('canonical', 'Microsoft Excel')->get()->getRowArray();
        $excelAliases = json_decode((string) $excel['aliases'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains('ms excel', $excelAliases);
        $this->assertContains('excel', $excelAliases);

        $admin = $this->db->table('title_dictionary')->where('canonical_title', 'Staf Administrasi')->get()->getRowArray();
        $adminAliases = json_decode((string) $admin['aliases'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains('admin', $adminAliases);
        $this->assertContains('office administrator', $adminAliases);
    }
}
