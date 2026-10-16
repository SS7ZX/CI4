<?php

declare(strict_types=1);

use App\Database\Seeds\CvExampleSeeder;
use App\Entities\Cv as CvEntity;
use App\Entities\CvExperience;
use App\Entities\CvSkillGroup;
use App\Models\Cv;
use App\Models\CvExperience as CvExperienceModel;
use App\Models\CvLink;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class CvSchemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = ['CodeIgniter\\Shield', 'App'];
    protected $refresh = false;

    public function testCvRelationsCascadeAndMigrationsRollbackCleanly(): void
    {
        $this->assertTrue($this->db->tableExists('users'));
        $this->assertTrue($this->db->tableExists('cvs'));
        $this->assertTrue($this->db->tableExists('cv_profiles'));

        $this->db->table('users')->insert([
            'username' => 'cascade-test',
            'active'   => 1,
        ]);
        $userId = (int) $this->db->insertID();
        $cvModel = new Cv();
        $cvId = $cvModel->insert([
            'user_id'      => $userId,
            'title'        => 'CV Relasi Test',
            'theme_key'    => 'ats-classic',
            'locale'       => 'id',
            'paper_size'   => 'a4',
            'font_scale'   => 'medium',
            'settings'     => ['show_photo' => false],
            'last_step'    => 1,
        ]);
        $this->assertIsString($cvId);
        $this->assertSame(CvEntity::class, (new ReflectionProperty(Cv::class, 'returnType'))->getValue($cvModel));
        $cv = $cvModel->find($cvId);
        $this->assertInstanceOf(CvEntity::class, $cv);
        $this->assertSame(['show_photo' => false], $cv->settings);
        $experienceModel = new CvExperienceModel();
        $experienceId = $experienceModel->insert([
            'cv_id'      => $cvId,
            'company'    => 'Perusahaan Test',
            'position'   => 'Analis',
            'start_date' => '2024-01-01',
            'bullets'    => ['Membuat laporan'],
            'sort_order' => 0,
        ]);
        $storedExperience = $experienceModel->find($experienceId);
        $this->assertSame(['Membuat laporan'], $storedExperience->bullets);

        $experience = new CvExperience(['bullets' => '["Membuat laporan"]']);
        $this->assertSame(['Membuat laporan'], $experience->bullets);
        $skillGroup = new CvSkillGroup(['items' => '["Analisis data"]']);
        $this->assertSame(['Analisis data'], $skillGroup->items);
        $invalidLink = new CvLink();
        $this->assertFalse($invalidLink->insert(['cv_id' => $cvId, 'label' => 'Portofolio']));
        $this->assertStringContainsString('wajib diisi', implode(' ', $invalidLink->errors()));
        $allowedFields = new ReflectionProperty(Cv::class, 'allowedFields');
        $allowedFields->setAccessible(true);
        $this->assertContains('user_id', $allowedFields->getValue(new Cv()));

        $this->db->table('cvs')->delete(['id' => $cvId]);
        $this->assertSame(0, $this->db->table('cv_experiences')->where('cv_id', $cvId)->countAllResults());

        $secondCvId = 'f18773af-c332-49eb-8dcc-7359f3ce0ac0';
        $this->db->table('cvs')->insert([
            'id'      => $secondCvId,
            'user_id' => $userId,
            'title'   => 'CV Cascade User',
        ]);
        $this->db->table('users')->delete(['id' => $userId]);
        $this->assertSame(0, $this->db->table('cvs')->where('id', $secondCvId)->countAllResults());

        $this->seeder->call(CvExampleSeeder::class);
        $this->assertSame(1, $this->db->table('cvs')->where('title', 'CV Contoh - Administrasi dan Keuangan')->countAllResults());
        foreach ([
            'cv_profiles',
            'cv_links',
            'cv_experiences',
            'cv_educations',
            'cv_skill_groups',
            'cv_projects',
            'cv_certifications',
            'cv_organizations',
            'cv_awards',
            'cv_languages',
            'cv_custom_sections',
            'cv_section_settings',
        ] as $table) {
            $this->assertGreaterThan(0, $this->db->table($table)->countAllResults(), $table . ' harus memiliki data contoh.');
        }

        $migrations = service('migrations');
        $migrations->setNamespace('App');
        $this->assertTrue($migrations->regress(0, 'tests'));
        $this->assertFalse($this->db->tableExists('cvs'));
        $this->assertTrue($migrations->latest('tests'));
        $this->assertTrue($this->db->tableExists('cvs'));
    }
}
