<?php

declare(strict_types=1);

use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Libraries\Jobs\MatchScorer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JobMatchGoldenTest extends TestCase
{
    public function testThreeExampleProfilesRankRelevantJobsFirst(): void
    {
        $scorer = new MatchScorer();
        $skills = [
            ['canonical' => 'Microsoft Excel', 'aliases' => ['excel', 'ms excel'], 'category' => 'office', 'weight' => 2],
            ['canonical' => 'Pengarsipan', 'aliases' => ['filing', 'records management'], 'category' => 'administration', 'weight' => 1],
            ['canonical' => 'PHP', 'aliases' => ['php'], 'category' => 'technology', 'weight' => 2],
            ['canonical' => 'SQL', 'aliases' => ['sql'], 'category' => 'technology', 'weight' => 2],
            ['canonical' => 'SEO', 'aliases' => ['search engine optimization'], 'category' => 'marketing', 'weight' => 1],
            ['canonical' => 'Google Ads', 'aliases' => ['adwords'], 'category' => 'marketing', 'weight' => 1],
        ];
        $titles = [
            ['canonical_title' => 'Staf Administrasi', 'aliases' => ['office administrator', 'admin'], 'job_family' => 'administration', 'seniority_hint' => null],
            ['canonical_title' => 'Software Engineer', 'aliases' => ['software developer', 'developer'], 'job_family' => 'technology', 'seniority_hint' => null],
            ['canonical_title' => 'Manajer Pemasaran', 'aliases' => ['marketing manager', 'marketing lead'], 'job_family' => 'marketing', 'seniority_hint' => 'senior'],
        ];
        $profiles = [
            'fresh_graduate_administration' => new CvSearchProfile(
                ['Staf Administrasi'], ['office administrator'],
                [
                    'Microsoft Excel' => ['weight' => 2, 'occurrences' => 2, 'category' => 'office'],
                    'Pengarsipan' => ['weight' => 1, 'occurrences' => 1, 'category' => 'administration'],
                ],
                0.4, 'fresh_graduate', 'Bandung', 'Diploma', 'Administrasi', [],
            ),
            'mid_level_developer' => new CvSearchProfile(
                ['Software Engineer'], ['software developer'],
                [
                    'PHP' => ['weight' => 3, 'occurrences' => 3, 'category' => 'technology'],
                    'SQL' => ['weight' => 2, 'occurrences' => 2, 'category' => 'technology'],
                ],
                4.0, 'mid', 'Jakarta Selatan', 'Sarjana', 'Informatika', [],
            ),
            'senior_marketing' => new CvSearchProfile(
                ['Manajer Pemasaran'], ['marketing manager'],
                [
                    'SEO' => ['weight' => 3, 'occurrences' => 3, 'category' => 'marketing'],
                    'Google Ads' => ['weight' => 2, 'occurrences' => 2, 'category' => 'marketing'],
                ],
                8.0, 'senior', 'Jakarta', 'Sarjana', 'Pemasaran', [],
            ),
        ];
        $postings = [
            'administration' => [
                'id' => 'job-admin',
                'title' => 'Office Administrator',
                'snippet' => str_repeat(
                    'Entry level. Microsoft Excel, filing, dan pengelolaan dokumen menjadi tanggung jawab pekerjaan ini. ',
                    4,
                ),
                'location' => 'Bandung',
                'is_remote' => false,
                'job_type' => 'full_time',
                'posted_at' => '2026-10-18 00:00:00',
                'salary_min_idr' => 5_000_000,
                'salary_max_idr' => 7_000_000,
            ],
            'developer' => [
                'id' => 'job-developer',
                'title' => 'Software Developer',
                'snippet' => str_repeat(
                    'Mid-level role. Develop services using PHP and SQL, review code, and maintain database performance. ',
                    4,
                ),
                'location' => 'Jakarta Selatan',
                'is_remote' => false,
                'job_type' => 'full_time',
                'posted_at' => '2026-10-17 00:00:00',
                'salary_min_idr' => 12_000_000,
                'salary_max_idr' => 18_000_000,
            ],
            'marketing' => [
                'id' => 'job-marketing',
                'title' => 'Marketing Manager',
                'snippet' => str_repeat(
                    'Senior role. Lead SEO and Google Ads campaigns, measure acquisition results, and guide the marketing team. ',
                    4,
                ),
                'location' => 'Jakarta',
                'is_remote' => false,
                'job_type' => 'full_time',
                'posted_at' => '2026-10-16 00:00:00',
                'salary_min_idr' => 20_000_000,
                'salary_max_idr' => 30_000_000,
            ],
        ];
        $preferences = [
            'fresh_graduate_administration' => [
                'target_titles' => ['Staf Administrasi'],
                'locations' => ['Bandung'],
                'remote_mode' => 'any',
                'job_types' => ['full_time'],
                'min_salary_idr' => 4_000_000,
                'excluded_keywords' => [],
            ],
            'mid_level_developer' => [
                'target_titles' => ['Software Engineer'],
                'locations' => ['Jakarta Selatan'],
                'remote_mode' => 'any',
                'job_types' => ['full_time'],
                'min_salary_idr' => 10_000_000,
                'excluded_keywords' => [],
            ],
            'senior_marketing' => [
                'target_titles' => ['Manajer Pemasaran'],
                'locations' => ['Jakarta'],
                'remote_mode' => 'any',
                'job_types' => ['full_time'],
                'min_salary_idr' => 18_000_000,
                'excluded_keywords' => [],
            ],
        ];

        $expectedTopJob = [
            'fresh_graduate_administration' => 'job-admin',
            'mid_level_developer' => 'job-developer',
            'senior_marketing' => 'job-marketing',
        ];
        foreach ($profiles as $profileKey => $profile) {
            $ranked = [];
            foreach ($postings as $posting) {
                $ranked[$posting['id']] = $scorer->score(
                    $profile,
                    $posting,
                    $preferences[$profileKey],
                    $skills,
                    $titles,
                    new DateTimeImmutable('2026-10-19T00:00:00+00:00'),
                );
            }
            uasort($ranked, static fn ($left, $right): int => $right->score <=> $left->score);

            $this->assertSame(
                $expectedTopJob[$profileKey],
                array_key_first($ranked),
                'Profil ' . $profileKey . ' harus menempatkan lowongan yang relevan pada urutan pertama.',
            );
            $this->assertGreaterThanOrEqual(75, reset($ranked)->score);
            $this->assertNotEmpty(reset($ranked)->breakdown);
        }
    }
}
