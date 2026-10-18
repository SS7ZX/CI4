<?php

declare(strict_types=1);

use App\Libraries\Jobs\CvProfileExtractor;
use App\Libraries\Jobs\LocationSanitizer;
use App\Libraries\Jobs\SeniorityEstimator;
use App\Libraries\Jobs\SkillExtractor;
use App\Libraries\Jobs\TitleMatcher;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JobProfileExtractionTest extends TestCase
{
    public function testSkillExtractorRecognizesLongAliasesAndWholeWords(): void
    {
        $extractor = new SkillExtractor();
        $skills = $extractor->extract(
            'Mengolah laporan dengan MS Excel dan Microsoft Excel. Saya bukan excelled.',
            [
                ['canonical' => 'Microsoft Excel', 'aliases' => ['ms excel', 'excel'], 'category' => 'office', 'weight' => 1],
            ],
        );

        $this->assertArrayHasKey('Microsoft Excel', $skills);
        $this->assertSame(2, $skills['Microsoft Excel']['occurrences']);
    }

    public function testTitleMatcherNormalizesIndonesianAndEnglishAliases(): void
    {
        $matcher = new TitleMatcher();
        $titles = [
            [
                'canonical_title' => 'Staf Administrasi',
                'aliases' => ['admin', 'office administrator'],
                'job_family' => 'administration',
                'seniority_hint' => null,
            ],
        ];

        $this->assertSame('Staf Administrasi', $matcher->match('  OFFICE administrator ', $titles)['canonical_title']);
        $this->assertNull($matcher->match('Dokter Spesialis', $titles));
    }

    public function testLocationSanitizerRejectsAddressesAndKeepsCityLevelNames(): void
    {
        $sanitizer = new LocationSanitizer();

        $this->assertSame('Jakarta Selatan', $sanitizer->normalizeCity('  Jakarta   Selatan '));
        $this->assertNull($sanitizer->normalizeCity('Jl. Sudirman No. 10, Jakarta'));
        $this->assertNull($sanitizer->normalizeCity('Bandung 40123'));
    }

    public function testSeniorityEstimatorMergesOverlappingPeriodsAndCurrentRole(): void
    {
        $estimator = new SeniorityEstimator();
        $result = $estimator->estimate([
            ['start_date' => '2020-01-01', 'end_date' => '2021-01-01', 'is_current' => false],
            ['start_date' => '2020-06-01', 'end_date' => '2022-01-01', 'is_current' => false],
            ['start_date' => '2024-01-01', 'end_date' => null, 'is_current' => true],
        ], new DateTimeImmutable('2025-01-01'));

        $this->assertSame(3.0, $result['years']);
        $this->assertSame('mid', $result['level']);
    }

    public function testFreshGraduateAndSeniorityBoundaries(): void
    {
        $estimator = new SeniorityEstimator();
        $this->assertSame(
            ['years' => 0.0, 'level' => 'fresh_graduate'],
            $estimator->estimate([], new DateTimeImmutable('2025-01-01')),
        );
        $this->assertSame(
            'junior',
            $estimator->estimate([
                ['start_date' => '2023-01-01', 'end_date' => '2024-12-31', 'is_current' => false],
            ], new DateTimeImmutable('2025-01-01'))['level'],
        );
    }

    public function testSeniorityBoundariesCanBeConfigured(): void
    {
        $estimator = new SeniorityEstimator(['junior' => 2, 'mid' => 4, 'senior' => 7, 'lead' => 12]);
        $result = $estimator->estimate([
            ['start_date' => '2023-01-01', 'end_date' => '2024-06-30', 'is_current' => false],
        ], new DateTimeImmutable('2025-01-01'));

        $this->assertSame('fresh_graduate', $result['level']);
    }

    public function testCvProfileExtractorBuildsSafeSearchProfileFromStructuredCv(): void
    {
        $profile = (new CvProfileExtractor())->extract([
            'profile' => [
                'full_name' => 'Rina Contoh',
                'headline' => 'Staf Administrasi dan Keuangan',
                'email' => 'rina@example.test',
                'city' => 'Bandung',
            ],
            'experiences' => [
                [
                    'position' => 'Staf Administrasi',
                    'company' => 'Perusahaan Rahasia',
                    'start_date' => '2022-01-01',
                    'end_date' => null,
                    'is_current' => true,
                    'bullets' => ['Menyiapkan laporan menggunakan Microsoft Excel.'],
                ],
            ],
            'skill_groups' => [
                ['name' => 'Administrasi', 'items' => ['Microsoft Excel', 'Pengarsipan']],
            ],
            'projects' => [],
            'certifications' => [
                ['name' => 'Sertifikat Microsoft Excel', 'issuer' => 'Lembaga Contoh'],
            ],
            'educations' => [
                ['degree' => 'Sarjana', 'field_of_study' => 'Akuntansi'],
            ],
        ], [
            ['canonical' => 'Microsoft Excel', 'aliases' => ['ms excel', 'excel'], 'category' => 'office', 'weight' => 1],
            ['canonical' => 'Pengarsipan', 'aliases' => ['records management', 'filing'], 'category' => 'administration', 'weight' => 1],
            ['canonical' => 'Administrasi', 'aliases' => ['administration'], 'category' => 'administration', 'weight' => 1],
        ], [
            [
                'canonical_title' => 'Staf Administrasi',
                'aliases' => ['admin', 'office administrator', 'staf administrasi dan keuangan'],
                'job_family' => 'administration',
                'seniority_hint' => null,
            ],
        ], new DateTimeImmutable('2025-01-01'));

        $this->assertTrue($profile->isReady());
        $this->assertSame(['Staf Administrasi'], $profile->targetTitles);
        $this->assertContains('office administrator', $profile->titleAliases);
        $this->assertArrayHasKey('Microsoft Excel', $profile->skills);
        $this->assertSame('Bandung', $profile->location);
        $this->assertSame('Sarjana', $profile->highestEducation);
        $this->assertSame('Akuntansi', $profile->educationField);
        $this->assertSame('mid', $profile->seniority);
        $serialized = json_encode($profile->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Rina Contoh', $serialized);
        $this->assertStringNotContainsString('Perusahaan Rahasia', $serialized);
        $this->assertStringNotContainsString('rina@example.test', $serialized);
    }

    public function testJobMatchingConfigurationIncludesSpecifiedDefaults(): void
    {
        $config = config('JobMatching');

        $this->assertSame(8, $config->providerTimeoutSeconds);
        $this->assertSame(2, $config->providerMaxRetries);
        $this->assertSame(6, $config->maxQueriesPerRefresh);
        $this->assertSame(43200, $config->queryCacheTtlSeconds);
        $this->assertSame([
            'title' => 30,
            'skill' => 35,
            'seniority' => 10,
            'location' => 15,
            'freshness' => 5,
            'salary' => 5,
        ], $config->scoreWeights);
        $this->assertSame(['very_good' => 75, 'good' => 55, 'partial' => 35], $config->scoreThresholds);
    }
}
