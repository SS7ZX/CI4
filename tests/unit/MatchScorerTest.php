<?php

declare(strict_types=1);

use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Libraries\Jobs\MatchScorer;
use Config\JobMatching;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MatchScorerTest extends TestCase
{
    private const SKILLS = [
        ['canonical' => 'Microsoft Excel', 'aliases' => ['excel', 'ms excel'], 'category' => 'office', 'weight' => 2],
        ['canonical' => 'Pengarsipan', 'aliases' => ['filing', 'records management'], 'category' => 'administration', 'weight' => 1],
        ['canonical' => 'PHP', 'aliases' => ['php'], 'category' => 'technology', 'weight' => 2],
        ['canonical' => 'SQL', 'aliases' => ['sql'], 'category' => 'technology', 'weight' => 2],
        ['canonical' => 'SEO', 'aliases' => ['search engine optimization'], 'category' => 'marketing', 'weight' => 1],
        ['canonical' => 'Google Ads', 'aliases' => ['adwords'], 'category' => 'marketing', 'weight' => 1],
    ];

    private const TITLES = [
        [
            'canonical_title' => 'Staf Administrasi',
            'aliases' => ['admin', 'office administrator'],
            'job_family' => 'administration',
            'seniority_hint' => null,
        ],
        [
            'canonical_title' => 'Software Engineer',
            'aliases' => ['developer', 'software developer'],
            'job_family' => 'technology',
            'seniority_hint' => null,
        ],
        [
            'canonical_title' => 'Manajer Pemasaran',
            'aliases' => ['marketing manager', 'marketing lead'],
            'job_family' => 'marketing',
            'seniority_hint' => 'senior',
        ],
    ];

    public function testTitleComponentRecognizesCanonicalAndAliasAndUsesTokenFamilyScores(): void
    {
        $scorer = new MatchScorer();

        $exact = $scorer->titleComponent('Office Administrator', ['Staf Administrasi'], self::TITLES);
        $similar = $scorer->titleComponent('Senior Software Developer', ['Software Engineer'], self::TITLES);

        $this->assertSame(100.0, $exact['score']);
        $this->assertStringContainsString('sama dengan jabatan target', $exact['reason']);
        $this->assertGreaterThan(0, $similar['score']);
        $this->assertLessThan(100, $similar['score']);
    }

    public function testSkillComponentUsesDemandedSkillsAndCapsSingleSkillMatch(): void
    {
        $scorer = new MatchScorer();
        $profileSkills = [
            'Microsoft Excel' => ['weight' => 2, 'occurrences' => 2, 'category' => 'office'],
        ];

        $single = $scorer->skillComponent('Microsoft Excel required', $profileSkills, self::SKILLS);
        $several = $scorer->skillComponent('Microsoft Excel and PHP required', $profileSkills, self::SKILLS);
        $weighted = $scorer->skillComponent('Microsoft Excel and PHP required', [
            'Microsoft Excel' => ['weight' => 3, 'occurrences' => 3, 'category' => 'office'],
        ], self::SKILLS);
        $missing = $scorer->skillComponent('PHP and SQL required', $profileSkills, self::SKILLS);
        $unknown = $scorer->skillComponent('Responsibilities include teamwork', $profileSkills, self::SKILLS);

        $this->assertSame(60.0, $single['component']['score']);
        $this->assertSame(['Microsoft Excel'], $single['matched']);
        $this->assertSame(['PHP'], $several['missing']);
        $this->assertGreaterThan(0, $several['component']['score']);
        $this->assertGreaterThan($several['component']['score'], $weighted['component']['score']);
        $this->assertSame([], $missing['matched']);
        $this->assertSame(['PHP', 'SQL'], $missing['missing']);
        $this->assertNull($unknown['component']);
    }

    public function testSeniorityComponentHandlesExactAndDistanceLevels(): void
    {
        $scorer = new MatchScorer();

        $same = $scorer->seniorityComponent('mid', 'Mid-level software engineer');
        $oneLevel = $scorer->seniorityComponent('mid', 'Senior software engineer');
        $far = $scorer->seniorityComponent('junior', 'Lead developer');
        $entry = $scorer->seniorityComponent('fresh_graduate', 'Internship trainee');
        $unknown = $scorer->seniorityComponent('mid', 'Software engineer');

        $this->assertSame(100.0, $same['score']);
        $this->assertSame(50.0, $oneLevel['score']);
        $this->assertSame(15.0, $far['score']);
        $this->assertSame(100.0, $entry['score']);
        $this->assertNull($unknown);
    }

    public function testLocationComponentConsidersCityAreaAndRemotePreference(): void
    {
        $scorer = new MatchScorer();

        $sameCity = $scorer->locationComponent('Bandung', false, ['locations' => ['Bandung'], 'remote_mode' => 'any']);
        $sameArea = $scorer->locationComponent('Bekasi', false, ['locations' => ['Jakarta Selatan'], 'remote_mode' => 'any']);
        $remote = $scorer->locationComponent('Remote', true, ['locations' => ['Bandung'], 'remote_mode' => 'remote']);
        $denied = $scorer->locationComponent('Jakarta', true, ['locations' => ['Jakarta'], 'remote_mode' => 'onsite']);

        $this->assertSame(100.0, $sameCity['score']);
        $this->assertSame(80.0, $sameArea['score']);
        $this->assertSame(100.0, $remote['score']);
        $this->assertSame(0.0, $denied['score']);
    }

    public function testFreshnessAndSalaryAreOmittedWhenDataIsUnavailable(): void
    {
        $scorer = new MatchScorer();
        $asOf = new DateTimeImmutable('2026-10-19T00:00:00+00:00');

        $fresh = $scorer->freshnessComponent(['posted_at' => '2026-10-19 00:00:00'], $asOf);
        $old = $scorer->freshnessComponent(['posted_at' => '2026-09-01 00:00:00'], $asOf);
        $missingFreshness = $scorer->freshnessComponent(['posted_at' => null], $asOf);
        $salary = $scorer->salaryComponent(['salary_min_idr' => 8_000_000], ['min_salary_idr' => 7_000_000]);
        $noSalary = $scorer->salaryComponent([], ['min_salary_idr' => 7_000_000]);
        $noPreference = $scorer->salaryComponent(['salary_min_idr' => 8_000_000], []);

        $this->assertSame(100.0, $fresh['score']);
        $this->assertSame(0.0, $old['score']);
        $this->assertNull($missingFreshness);
        $this->assertSame(100.0, $salary['score']);
        $this->assertNull($noSalary);
        $this->assertNull($noPreference);
    }

    public function testScoringNormalizesAvailableWeightsAppliesPenaltiesAndIncludesExplanation(): void
    {
        $scorer = new MatchScorer();
        $profile = $this->profile(['Microsoft Excel' => ['weight' => 2, 'occurrences' => 1, 'category' => 'office']]);
        $posting = [
            'title' => 'Staf Administrasi',
            'snippet' => str_repeat('Membutuhkan Microsoft Excel dan pengarsipan. ', 6),
            'location' => 'Bandung',
            'is_remote' => false,
            'job_type' => 'contract',
            'posted_at' => '2026-10-19 00:00:00',
        ];
        $preferences = [
            'target_titles' => ['Staf Administrasi'],
            'locations' => ['Bandung'],
            'remote_mode' => 'any',
            'job_types' => ['full_time'],
            'excluded_keywords' => ['kerja shift'],
        ];
        $result = $scorer->score(
            $profile,
            $posting,
            $preferences,
            self::SKILLS,
            self::TITLES,
            new DateTimeImmutable('2026-10-19T00:00:00+00:00'),
        );

        $this->assertSame('good', $result->label);
        $this->assertSame('tinggi', $result->confidence);
        $this->assertArrayHasKey('title', $result->breakdown);
        $this->assertArrayHasKey('skill', $result->breakdown);
        $this->assertArrayHasKey('penalties', $result->breakdown);
        $this->assertSame(-15.0, $result->breakdown['penalties']['score']);
        $this->assertNotEmpty($result->reasons);
        $this->assertLessThanOrEqual(5, count($result->reasons));
        $this->assertFalse($result->excludedKeywordMatch);

        $excluded = $scorer->score(
            $profile,
            [...$posting, 'snippet' => str_repeat('kerja shift dan Microsoft Excel. ', 8)],
            [...$preferences, 'job_types' => ['contract']],
            self::SKILLS,
            self::TITLES,
            new DateTimeImmutable('2026-10-19T00:00:00+00:00'),
        );
        $this->assertTrue($excluded->excludedKeywordMatch);
        $this->assertArrayHasKey('penalties', $excluded->breakdown);
        $this->assertSame('good', $result->label);
    }

    public function testMissingComponentsAreRemovedBeforeWeightNormalizationAndConfidenceUsesCount(): void
    {
        $config = new JobMatching();
        $config->minimumSnippetLength = 20;
        $scorer = new MatchScorer($config);
        $profile = $this->profile([]);
        $result = $scorer->score(
            $profile,
            [
                'title' => 'Office Administrator',
                'snippet' => str_repeat('Entry level office administrator manages documents. ', 2),
                'location' => 'Bandung',
                'is_remote' => false,
                'job_type' => null,
                'posted_at' => null,
                'salary_min_idr' => null,
            ],
            ['target_titles' => ['Staf Administrasi'], 'locations' => ['Bandung'], 'remote_mode' => 'any'],
            [],
            self::TITLES,
        );

        $this->assertSame(['title', 'seniority', 'location'], array_keys($result->breakdown));
        $this->assertSame(100, $result->score);
        $this->assertSame('sedang', $result->confidence);
        $this->assertSame('very_good', $result->label);
    }

    public function testLowConfidenceAndLowScoreLabelsAreConfigurable(): void
    {
        $config = new JobMatching();
        $config->minimumSnippetLength = 500;
        $config->hideLowScoreMatches = true;
        $scorer = new MatchScorer($config);
        $result = $scorer->score(
            $this->profile([]),
            ['title' => 'Warehouse Worker', 'snippet' => 'short', 'location' => 'Jakarta'],
            ['target_titles' => ['Manajer Pemasaran'], 'locations' => ['Bandung'], 'remote_mode' => 'onsite'],
            [],
            self::TITLES,
        );

        $this->assertSame('rendah', $result->confidence);
        $this->assertSame('low', $result->label);
        $this->assertTrue($result->hidden);
        $this->assertContains(lang('Jobs.score_reason_confidence_snippet'), $result->reasons);
    }

    /**
     * @param array<string, array{weight: int, occurrences: int, category: string}> $skills
     */
    private function profile(array $skills): CvSearchProfile
    {
        return new CvSearchProfile(
            ['Staf Administrasi'],
            ['office administrator'],
            $skills,
            0.4,
            'fresh_graduate',
            'Bandung',
            null,
            null,
            [],
        );
    }
}
