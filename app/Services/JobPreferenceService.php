<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Jobs\CvProfileExtractor;
use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Libraries\Jobs\LocationSanitizer;
use App\Libraries\Jobs\SeniorityEstimator;
use App\Libraries\Jobs\TitleMatcher;
use App\Repositories\JobDictionaryRepository;
use App\Repositories\JobPreferenceRepository;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;

final class JobPreferenceService
{
    private readonly JobPreferenceRepository $preferences;
    private readonly JobDictionaryRepository $dictionaries;
    private readonly CvBuilderService $cvBuilder;
    private readonly CvProfileExtractor $profileExtractor;
    private readonly LocationSanitizer $locationSanitizer;
    private readonly TitleMatcher $titleMatcher;

    public function __construct(
        ?JobPreferenceRepository $preferences = null,
        ?JobDictionaryRepository $dictionaries = null,
        ?CvBuilderService $cvBuilder = null,
        ?CvProfileExtractor $profileExtractor = null,
        ?LocationSanitizer $locationSanitizer = null,
        ?TitleMatcher $titleMatcher = null,
    ) {
        $this->preferences = $preferences ?? new JobPreferenceRepository();
        $this->dictionaries = $dictionaries ?? new JobDictionaryRepository();
        $this->cvBuilder = $cvBuilder ?? new CvBuilderService();
        $this->profileExtractor = $profileExtractor ?? new CvProfileExtractor(
            seniorityEstimator: new SeniorityEstimator(config('JobMatching')->seniorityBoundariesYears),
        );
        $this->locationSanitizer = $locationSanitizer ?? new LocationSanitizer();
        $this->titleMatcher = $titleMatcher ?? new TitleMatcher();
    }

    /**
     * @return array{cv: object, cvData: array<string, mixed>, preference: array<string, mixed>|null, suggestedPreferences: array<string, mixed>, searchPreferences: array<string, mixed>, profile: CvSearchProfile, hasConsent: bool}
     */
    public function workflow(int|string $userId, string $cvId): array
    {
        $cvData = $this->cvBuilder->load($userId, $cvId);
        $preference = $this->preferences->findForCv($cvId);
        $skillsDictionary = $this->dictionaries->skills();
        $titleDictionary = $this->dictionaries->titles();
        $profile = $this->profileExtractor->extract(
            $cvData,
            $skillsDictionary,
            $titleDictionary,
        );
        $suggestedPreferences = $this->defaultPreferences($profile);
        $searchPreferences = $preference ?? $suggestedPreferences;
        $searchPreferences['locations'] = array_values(array_filter(
            $searchPreferences['locations'] ?? [],
            fn (string $city): bool => ! $this->isCvPersonalData($city, $cvData),
        ));

        return [
            'cv'         => $cvData['cv'],
            'cvData'     => $cvData,
            'preference' => $preference,
            'suggestedPreferences' => $suggestedPreferences,
            'searchPreferences' => $searchPreferences,
            'profile'    => $profile,
            'titleSuggestions' => array_values(array_unique(array_merge(
                array_column($titleDictionary, 'canonical_title'),
                ...array_map(static fn (array $row): array => $row['aliases'], $titleDictionary),
            ))),
            'titleDictionary' => $titleDictionary,
            'hasConsent' => $this->hasConsent($preference),
        ];
    }

    public function grantConsent(int|string $userId, string $cvId): void
    {
        $workflow = $this->workflow($userId, $cvId);
        $profile  = $workflow['profile'];
        $current  = $workflow['preference'];
        $defaults = $this->defaultPreferences($profile);
        if ($current !== null) {
            $defaults = array_replace($defaults, array_intersect_key($current, $defaults));
        }
        $defaults['search_enabled'] = true;
        $defaults['consent_at'] = gmdate('Y-m-d H:i:s');

        $this->preferences->save($cvId, $defaults);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function savePreferences(int|string $userId, string $cvId, array $input): void
    {
        $workflow = $this->workflow($userId, $cvId);
        if (! $workflow['hasConsent']) {
            throw new JobConsentRequiredException();
        }

        $config = config('JobMatching');
        $targetTitles = $this->stringList($input['target_titles'] ?? [], 10, 160);
        $data = [
            'target_titles' => $targetTitles === false
                ? false
                : $this->normalizeTargetTitles($targetTitles, $workflow['titleDictionary']),
            'locations' => $this->cityList($input['locations'] ?? []),
            'remote_mode' => $this->scalarString($input['remote_mode'] ?? null),
            'job_types' => $this->stringList($input['job_types'] ?? [], 5, 24),
            'min_salary_idr' => $this->parseSalary($input['min_salary_idr'] ?? ''),
            'excluded_keywords' => $this->stringList($input['excluded_keywords'] ?? [], 20, 80),
            'experience_level_override' => $this->scalarString($input['experience_level_override'] ?? null),
        ];

        $errors = [];
        if ($data['target_titles'] === []) {
            $errors['target_titles'] = lang('Jobs.title_required');
        } elseif ($data['target_titles'] === false) {
            $errors['target_titles'] = lang('Jobs.target_title_invalid');
        }
        if ($data['locations'] === []) {
            $errors['locations'] = lang('Jobs.location_required');
        }
        if (is_array($data['locations']) && $this->containsCvPersonalData($data['locations'], $this->cvBuilder->load($userId, $cvId))) {
            $errors['locations'] = lang('Jobs.location_personal_data_invalid');
        }
        if (! in_array($data['remote_mode'], $config->allowedRemoteModes, true)) {
            $errors['remote_mode'] = lang('Jobs.remote_mode_invalid');
        }
        if ($data['job_types'] === [] || array_diff($data['job_types'], $config->allowedJobTypes) !== []) {
            $errors['job_types'] = lang('Jobs.job_types_invalid');
        }
        if ($data['min_salary_idr'] === false) {
            $errors['min_salary_idr'] = lang('Jobs.salary_invalid');
        }
        if ($data['experience_level_override'] !== '' && ! in_array(
            $data['experience_level_override'],
            $config->allowedSeniorityOverrides,
            true,
        )) {
            $errors['experience_level_override'] = lang('Jobs.seniority_invalid');
        }
        foreach (['target_titles', 'locations', 'job_types', 'excluded_keywords'] as $field) {
            if ($data[$field] === false) {
                $errors[$field] = lang('Jobs.list_invalid');
            }
        }
        if ($errors !== []) {
            throw new JobPreferenceValidationException($errors);
        }

        $current = $workflow['preference'];
        $this->preferences->save($cvId, [
            ...$data,
            'experience_level_override' => $data['experience_level_override'] !== '' ? $data['experience_level_override'] : null,
            'min_salary_idr' => $data['min_salary_idr'] === null ? null : (int) $data['min_salary_idr'],
            'search_enabled' => true,
            'consent_at' => $current['consent_at'],
        ]);
        (new MatchService())->recomputeForCv($userId, $cvId);
    }

    public function revokeConsent(int|string $userId, string $cvId): void
    {
        $this->cvBuilder->findOwned($userId, $cvId)
            ?? throw PageNotFoundException::forPageNotFound();
        $preference = $this->preferences->findForCv($cvId);
        if ($preference === null) {
            return;
        }

        $database = db_connect();
        $database->transBegin();
        try {
            $this->preferences->save($cvId, [
                'target_titles' => $preference['target_titles'],
                'locations' => $preference['locations'],
                'remote_mode' => $preference['remote_mode'],
                'job_types' => $preference['job_types'],
                'min_salary_idr' => $preference['min_salary_idr'],
                'excluded_keywords' => $preference['excluded_keywords'],
                'experience_level_override' => $preference['experience_level_override'],
                'search_enabled' => false,
                'consent_at' => null,
            ]);
            $this->preferences->deleteMatchesForCv($cvId);
            $database->transCommit();
        } catch (\Throwable $exception) {
            $database->transRollback();

            throw $exception;
        }
    }

    public function hasConsent(?array $preference): bool
    {
        return $preference !== null
            && (bool) $preference['search_enabled']
            && is_string($preference['consent_at'])
            && $preference['consent_at'] !== '';
    }

    public function assertSearchAllowed(int|string $userId, string $cvId): void
    {
        $workflow = $this->workflow($userId, $cvId);
        if (! $workflow['hasConsent']) {
            throw new JobConsentRequiredException();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPreferences(CvSearchProfile $profile): array
    {
        return [
            'target_titles' => array_slice($profile->targetTitles, 0, 10),
            'locations' => $profile->location !== null ? [$profile->location] : [],
            'remote_mode' => 'any',
            'job_types' => ['full_time'],
            'min_salary_idr' => null,
            'excluded_keywords' => [],
            'experience_level_override' => null,
            'search_enabled' => false,
            'consent_at' => null,
        ];
    }

    /**
     * @return list<string>|false
     */
    private function stringList(mixed $value, int $limit, int $maxLength): array|false
    {
        if (! is_array($value) || count($value) > $limit) {
            return false;
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (mb_strlen($item, 'UTF-8') > $maxLength) {
                return false;
            }
            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    private function parseSalary(mixed $value): int|false|null
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_string($value) && preg_match('/\A[0-9]{1,10}\z/', $value) === 1) {
            $value = (int) $value;
        }
        if (! is_int($value) || $value < 0 || $value > 1_000_000_000) {
            return false;
        }

        return $value === 0 ? null : $value;
    }

    private function scalarString(mixed $value): string|false
    {
        return is_string($value) ? trim($value) : false;
    }

    /**
     * @param list<string> $titles
     * @param list<array{canonical_title: string, aliases: list<string>, job_family: string, seniority_hint: string|null}> $dictionary
     *
     * @return list<string>|false
     */
    private function normalizeTargetTitles(array $titles, array $dictionary): array|false
    {
        $canonicalTitles = [];
        foreach ($titles as $title) {
            if (preg_match('/(?:@|https?:\/\/|\d{6,}|\b(?:pt|cv|company|perusahaan)\b)/iu', $title) === 1) {
                return false;
            }
            $match = $this->titleMatcher->match($title, $dictionary);
            if ($match === null) {
                return false;
            }
            $canonicalTitles[] = $match['canonical_title'];
        }

        return array_values(array_unique($canonicalTitles));
    }

    /**
     * @return list<string>|false
     */
    private function cityList(mixed $value): array|false
    {
        if (! is_array($value) || count($value) > 10) {
            return false;
        }

        $cities = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
            if (trim($item) === '') {
                continue;
            }
            $city = $this->locationSanitizer->normalizeCity($item);
            if ($city === null) {
                return false;
            }
            $cities[] = $city;
        }

        return array_values(array_unique($cities));
    }

    /**
     * @param list<string> $cities
     * @param array<string, mixed> $cvData
     */
    private function containsCvPersonalData(array $cities, array $cvData): bool
    {
        foreach ($cities as $city) {
            if ($this->isCvPersonalData($city, $cvData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $cvData
     */
    private function isCvPersonalData(string $city, array $cvData): bool
    {
        $profile = $this->recordToArray($cvData['profile'] ?? []);
        $sensitiveValues = [
            $profile['full_name'] ?? null,
            $profile['email'] ?? null,
            $profile['phone'] ?? null,
        ];
        foreach ($cvData['experiences'] ?? [] as $experience) {
            $sensitiveValues[] = $this->recordToArray($experience)['company'] ?? null;
        }
        $normalizedCity = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $city) ?? ''), 'UTF-8');
        foreach ($sensitiveValues as $sensitiveValue) {
            if (! is_string($sensitiveValue) || trim($sensitiveValue) === '') {
                continue;
            }
            $sensitiveValue = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $sensitiveValue) ?? ''), 'UTF-8');
            if (mb_strlen($sensitiveValue, 'UTF-8') >= 3 && str_contains($normalizedCity, $sensitiveValue)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value) && method_exists($value, 'toRawArray')) {
            return $value->toRawArray();
        }

        return [];
    }
}
