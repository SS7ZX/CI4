<?php

declare(strict_types=1);

use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Services\KeywordCheckService;
use App\Services\SkillGapService;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JobInsightServicesTest extends TestCase
{
    public function testSkillGapCountsTopRelevantJobsAndHidesSalaryBelowSampleThreshold(): void
    {
        $profile = $this->profile();
        $matches = [
            ['score' => 90, 'missing_skills' => '["SQL","Microsoft Excel"]', 'salary_min_idr' => 5_000_000, 'salary_max_idr' => 8_000_000],
            ['score' => 80, 'missing_skills' => '["SQL","Pengarsipan"]', 'salary_min_idr' => 6_000_000, 'salary_max_idr' => 9_000_000],
            ['score' => 70, 'missing_skills' => '["SQL"]', 'salary_min_idr' => 7_000_000, 'salary_max_idr' => 10_000_000],
            ['score' => 65, 'missing_skills' => '["SQL"]', 'salary_min_idr' => 8_000_000, 'salary_max_idr' => 11_000_000],
            ['score' => 60, 'missing_skills' => '["SQL"]', 'salary_min_idr' => 9_000_000, 'salary_max_idr' => 12_000_000],
            ['score' => 20, 'missing_skills' => '["Power BI"]', 'salary_min_idr' => 20_000_000, 'salary_max_idr' => 25_000_000],
        ];

        $insights = (new SkillGapService())->analyze($profile, $matches, [
            ['canonical' => 'Microsoft Excel', 'aliases' => ['excel']],
            ['canonical' => 'Filing', 'aliases' => ['pengarsipan']],
        ]);

        $this->assertSame([['skill' => 'SQL', 'count' => 5]], $insights['skills']);
        $this->assertSame(5, $insights['salary']['sample_count']);
        $this->assertSame(7_000_000, $insights['salary']['median_minimum']);
        $this->assertSame(10_000_000, $insights['salary']['median_maximum']);
    }

    public function testSalaryInsightDoesNotShowValuesBelowMinimumSamples(): void
    {
        $insights = (new SkillGapService())->analyze($this->profile(), [
            ['score' => 90, 'missing_skills' => '[]', 'salary_min_idr' => 5_000_000, 'salary_max_idr' => 8_000_000],
            ['score' => 90, 'missing_skills' => '[]', 'salary_min_idr' => 7_000_000, 'salary_max_idr' => 10_000_000],
        ]);

        $this->assertSame(2, $insights['salary']['sample_count']);
        $this->assertNull($insights['salary']['minimum']);
        $this->assertNull($insights['salary']['median_minimum']);
    }

    public function testKeywordCheckOnlySuggestsSectionsForUnverifiedKeywords(): void
    {
        $cv = [
            'profile' => ['headline' => 'Pengembang PHP'],
            'skill_groups' => [['items' => ['PHP']]],
            'experiences' => [['company' => 'SQL', 'bullets' => ['Membuat aplikasi web']]],
        ];
        $dictionary = [
            ['canonical' => 'PHP', 'aliases' => ['php language'], 'category' => 'technology'],
            ['canonical' => 'SQL', 'aliases' => ['structured query language'], 'category' => 'technology'],
        ];

        $checks = (new KeywordCheckService())->check($cv, ['PHP', 'SQL'], $dictionary);

        $this->assertSame(
            [
                ['keyword' => 'PHP', 'present' => true, 'sections' => []],
                ['keyword' => 'SQL', 'present' => false, 'sections' => ['skills', 'projects', 'experience']],
            ],
            $checks,
        );
    }

    private function profile(): CvSearchProfile
    {
        return new CvSearchProfile(
            ['Analyst'],
            [],
            [
                'Microsoft Excel' => ['weight' => 1, 'occurrences' => 1, 'category' => 'office'],
                'Filing' => ['weight' => 1, 'occurrences' => 1, 'category' => 'administration'],
            ],
            2.0,
            'junior',
            'Bandung',
            null,
            null,
            [],
        );
    }
}
