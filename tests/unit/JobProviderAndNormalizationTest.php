<?php

declare(strict_types=1);

use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Libraries\Jobs\Dto\JobPostingDto;
use App\Libraries\Jobs\Dto\SearchQueryDto;
use App\Libraries\Jobs\JobHttpClientInterface;
use App\Libraries\Jobs\JobNormalizer;
use App\Libraries\Jobs\SalaryParser;
use App\Libraries\Jobs\SearchQueryBuilder;
use App\Libraries\Jobs\Providers\JoobleProvider;
use CodeIgniter\HTTP\ResponseInterface;
use Config\JobMatching;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JobProviderAndNormalizationTest extends TestCase
{
    public function testSearchQueryContainsOnlyCanonicalJobTermsAndCity(): void
    {
        $profile = new CvSearchProfile(
            ['Software Engineer'],
            [],
            ['PHP' => ['weight' => 1, 'occurrences' => 1, 'category' => 'technology']],
            4.0,
            'mid',
            'Bandung',
            null,
            null,
            [],
        );
        $queries = (new SearchQueryBuilder())->build($profile, [
            'target_titles' => ['Software Engineer'],
            'locations' => ['Bandung'],
        ]);
        $queryText = $queries[0]->keywords . ' ' . $queries[0]->location;

        $this->assertCount(1, $queries);
        $this->assertSame('Software Engineer PHP', $queries[0]->keywords);
        $this->assertStringNotContainsString('Rina Contoh', $queryText);
        $this->assertStringNotContainsString('rina@example.test', $queryText);
        $this->assertStringNotContainsString('Perusahaan Rahasia', $queryText);
    }

    public function testSalaryParserDoesNotTreatUnavailableOrForeignSalaryAsZero(): void
    {
        $parser = new SalaryParser();

        $this->assertFalse($parser->parse(null)->comparable);
        $this->assertNull($parser->parse(null)->minimumIdr);
        $this->assertSame(5_000_000, $parser->parse('Rp 5.000.000 - Rp 8.000.000 per bulan')->minimumIdr);
        $this->assertSame(8_000_000, $parser->parse('Rp 5.000.000 - Rp 8.000.000 per bulan')->maximumIdr);
        $this->assertSame(5_000_000, $parser->parse('Rp 5 - 8 juta per bulan')->minimumIdr);
        $this->assertSame(8_000_000, $parser->parse('Rp 5 - 8 juta per bulan')->maximumIdr);
        $this->assertSame(5_000_000, $parser->parse('Rp 60.000.000 per tahun')->minimumIdr);
        $this->assertFalse($parser->parse('USD 1,000 per month')->comparable);
        $this->assertFalse($parser->parse('Rp 100.000 per jam')->comparable);
    }

    public function testNormalizerRejectsUnsafeLinksAndStoresOnlyAllowlistedRawFields(): void
    {
        $normalizer = new JobNormalizer();
        $unsafe = new JobPostingDto(null, 'Engineer', null, null, '', null, null, null, 'javascript:alert(1)', null, []);
        $this->assertNull($normalizer->normalize($unsafe, 'jooble', 3600));

        $posting = new JobPostingDto(
            'job-1',
            '<strong>Software Engineer</strong>',
            'Perusahaan Contoh',
            'Bandung',
            '<p>Membangun layanan.</p>',
            'Rp 10.000.000 per bulan',
            'Jooble',
            'full-time',
            'https://example.test/jobs/1',
            null,
            ['email' => 'private@example.test'],
        );
        $normalized = $normalizer->normalize($posting, 'jooble', 3600);

        $this->assertNotNull($normalized);
        $this->assertSame('Software Engineer', $normalized['title']);
        $this->assertSame(10_000_000, $normalized['salary_min_idr']);
        $this->assertSame('full_time', $normalized['job_type']);
        $this->assertStringNotContainsString('private@example.test', $normalized['raw']);
    }

    public function testJoobleProviderRetriesServerErrorsAndToleratesMissingFields(): void
    {
        $serverError = $this->createMock(ResponseInterface::class);
        $serverError->method('getStatusCode')->willReturn(503);
        $success = $this->createMock(ResponseInterface::class);
        $success->method('getStatusCode')->willReturn(200);
        $success->method('getBody')->willReturn(json_encode([
            'totalCount' => 2,
            'jobs' => [
                ['id' => 'one', 'title' => 'Engineer', 'link' => 'https://example.test/1'],
                ['id' => 'two', 'link' => 'https://example.test/2'],
                ['id' => 'three', 'title' => 'Missing link'],
            ],
        ], JSON_THROW_ON_ERROR));

        $config = new JobMatching();
        $config->joobleApiKey = 'secret-test-key';
        $config->providerMaxRetries = 1;
        $config->retryBackoffMilliseconds = [0];
        $http = $this->createMock(JobHttpClientInterface::class);
        $http->expects($this->exactly(2))
            ->method('post')
            ->with(
                $this->stringContains('/secret-test-key'),
                $this->callback(static function (array $payload): bool {
                    return $payload === [
                        'keywords' => 'Software Engineer PHP',
                        'location' => 'Bandung',
                        'page' => 1,
                    ];
                }),
                8,
            )
            ->willReturnOnConsecutiveCalls($serverError, $success);

        $result = (new JoobleProvider($config, $http))->search(
            new SearchQueryDto('Software Engineer PHP', 'Bandung'),
        );

        $this->assertCount(1, $result->postings);
        $this->assertSame('Engineer', $result->postings[0]->title);
        $this->assertSame(2, $result->totalCount);
        $this->assertCount(2, $result->warnings);
    }
}
