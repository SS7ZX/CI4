<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class JobMatching extends BaseConfig
{
    public int $providerTimeoutSeconds = 8;
    public int $providerMaxRetries = 2;
    public array $retryBackoffMilliseconds = [250, 750];
    public int $dailySearchLimit = 10;
    public int $cvRefreshCooldownSeconds = 300;
    public int $maxQueriesPerRefresh = 6;
    public int $queryCacheTtlSeconds = 43200;
    public int $postingRetentionSeconds = 604800;
    public int $minimumSnippetLength = 200;
    public int $maxSnippetLength = 5000;
    public int $minimumSkillComponentScore = 20;
    public int $excludedKeywordPenalty = 25;
    public bool $hideExcludedKeywordMatches = false;
    public int $incompatibleJobTypePenalty = 15;
    public int $topJobsForSkillGap = 30;
    public int $minimumScoreForSkillGap = 50;
    public int $minimumSalarySamplesForSummary = 5;
    public int $maximumReasonCount = 5;
    public bool $hideLowScoreMatches = true;
    public int $freshnessFullScoreDays = 0;
    public int $freshnessLowScoreDays = 30;
    public int $singleSkillMaximumScore = 60;
    public int $lowMatchScoreMaximum = 34;

    /** @var array<string, int> */
    public array $scoreWeights = [
        'title'     => 30,
        'skill'     => 35,
        'seniority' => 10,
        'location'  => 15,
        'freshness' => 5,
        'salary'    => 5,
    ];

    /** @var array<string, int> */
    public array $scoreThresholds = [
        'very_good' => 75,
        'good'      => 55,
        'partial'   => 35,
    ];

    /** @var array<string, int> */
    public array $titleSimilarityWeights = [
        'canonical_or_alias' => 60,
        'token_jaccard'      => 25,
        'job_family'         => 15,
    ];

    /** @var array<int|string, int> */
    public array $seniorityDistanceScores = [
        0   => 100,
        1   => 50,
        '2+' => 15,
    ];

    /** @var array<string, int> */
    public array $locationScores = [
        'same_city'    => 100,
        'same_area'    => 80,
        'remote'       => 100,
        'other'        => 20,
        'remote_denied' => 0,
    ];

    /** @var array<string, int> */
    public array $salaryScores = [
        'meets_minimum' => 100,
        'below_minimum' => 0,
    ];

    /** @var array<string, int> */
    public array $seniorityBoundariesYears = [
        'junior' => 1,
        'mid'    => 3,
        'senior' => 6,
        'lead'   => 10,
    ];

    /** @var array<string, int> */
    public array $confidenceMinimumComponents = [
        'medium' => 3,
        'high'   => 4,
    ];

    /** @var array<string, string> */
    public array $scoreLabels = [
        'very_good' => 'very_good',
        'good'      => 'good',
        'partial'   => 'partial',
        'low'       => 'low',
    ];

    /** @var array<string, list<string>> */
    public array $locationAreas = [
        'jabodetabek' => ['jakarta', 'jakarta pusat', 'jakarta utara', 'jakarta barat', 'jakarta selatan', 'jakarta timur', 'bogor', 'depok', 'tangerang', 'tangerang selatan', 'bekasi'],
        'bandung raya' => ['bandung', 'bandung barat', 'cimahi', 'sumedang'],
        'surabaya raya' => ['surabaya', 'sidoarjo', 'gresik'],
        'yogyakarta raya' => ['yogyakarta', 'sleman', 'bantul', 'kulon progo', 'gunungkidul'],
    ];

    /** @var array<string, string> */
    public array $seniorityAliases = [
        'intern' => 'intern',
        'internship' => 'intern',
        'magang' => 'intern',
        'trainee' => 'intern',
        'fresh graduate' => 'fresh_graduate',
        'entry level' => 'fresh_graduate',
        'lulusan baru' => 'fresh_graduate',
        'junior' => 'junior',
        'menengah' => 'mid',
        'mid level' => 'mid',
        'middle' => 'mid',
        'senior' => 'senior',
        'lead' => 'lead',
        'manager' => 'lead',
        'manajer' => 'lead',
        'head' => 'lead',
        'kepala' => 'lead',
    ];

    /** @var list<string> */
    public array $allowedRemoteModes = ['any', 'remote', 'hybrid', 'onsite'];

    /** @var list<string> */
    public array $allowedJobTypes = ['full_time', 'part_time', 'contract', 'internship', 'freelance'];

    /** @var list<string> */
    public array $allowedSeniorityOverrides = ['intern', 'fresh_graduate', 'junior', 'mid', 'senior', 'lead'];

    /** @var list<string> */
    public array $stopwords = [
        'dan', 'di', 'dengan', 'untuk', 'yang', 'the', 'and', 'for', 'of', 'in', 'to',
    ];

    /** @var list<class-string> */
    public array $activeProviders = [
        \App\Libraries\Jobs\Providers\JoobleProvider::class,
    ];

    public string $joobleApiKey = '';

    public bool $allowHttpJobProviders = false;

    public int $defaultJobResultsPerQuery = 20;

    public function __construct()
    {
        parent::__construct();
        $this->joobleApiKey = (string) env('jobMatching.joobleApiKey', '');
    }
}
