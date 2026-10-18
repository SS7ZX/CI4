<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Libraries\Jobs\Dto\CvSearchProfile;
use App\Libraries\Jobs\Dto\MatchResult;
use Config\JobMatching;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class MatchScorer
{
    private readonly TitleMatcher $titleMatcher;
    private readonly SkillExtractor $skillExtractor;

    public function __construct(
        private readonly JobMatching $config = new JobMatching(),
        ?TitleMatcher $titleMatcher = null,
        ?SkillExtractor $skillExtractor = null,
    ) {
        $this->titleMatcher = $titleMatcher ?? new TitleMatcher();
        $this->skillExtractor = $skillExtractor ?? new SkillExtractor();
    }

    /**
     * @param array<string, mixed> $posting
     * @param array<string, mixed> $preferences
     * @param list<array{canonical: string, aliases: list<string>, category?: string, weight?: int}> $skillsDictionary
     * @param list<array{canonical_title: string, aliases: list<string>, job_family?: string, seniority_hint?: string|null}> $titleDictionary
     */
    public function score(
        CvSearchProfile $profile,
        array $posting,
        array $preferences,
        array $skillsDictionary,
        array $titleDictionary,
        ?DateTimeImmutable $asOf = null,
    ): MatchResult {
        $weights = $this->normalizedWeights();
        $breakdown = [];
        $matchedSkills = [];
        $missingSkills = [];

        $this->addComponent($breakdown, 'title', $this->titleComponent(
            (string) ($posting['title'] ?? ''),
            $preferences['target_titles'] ?? $profile->targetTitles,
            $titleDictionary,
        ), $weights);

        $skillResult = $this->skillComponent(
            (string) ($posting['title'] ?? '') . ' ' . (string) ($posting['snippet'] ?? ''),
            $profile->skills,
            $skillsDictionary,
        );
        $matchedSkills = $skillResult['matched'];
        $missingSkills = $skillResult['missing'];
        $this->addComponent($breakdown, 'skill', $skillResult['component'], $weights);

        $cvSeniority = $this->seniorityLevel(
            (string) ($preferences['experience_level_override'] ?? $profile->seniority),
        );
        $this->addComponent($breakdown, 'seniority', $this->seniorityComponent(
            $cvSeniority,
            (string) ($posting['title'] ?? '') . ' ' . (string) ($posting['snippet'] ?? ''),
        ), $weights);

        $this->addComponent($breakdown, 'location', $this->locationComponent(
            (string) ($posting['location'] ?? ''),
            $posting['is_remote'] ?? null,
            $preferences,
        ), $weights);

        $this->addComponent($breakdown, 'freshness', $this->freshnessComponent($posting, $asOf), $weights);
        $this->addComponent($breakdown, 'salary', $this->salaryComponent($posting, $preferences), $weights);

        $totalWeight = 0;
        $weightedScore = 0.0;
        foreach ($breakdown as $component) {
            $totalWeight += $component['weight'];
            $weightedScore += $component['score'] * $component['weight'];
        }
        $score = $totalWeight > 0 ? (int) round($weightedScore / $totalWeight) : 0;
        if ($totalWeight > 0) {
            foreach ($breakdown as &$component) {
                $component['normalized_weight'] = round($component['weight'] * 100 / $totalWeight, 2);
            }
            unset($component);
        }

        $penalties = [];
        $excludedMatch = $this->hasExcludedKeyword(
            (string) ($posting['title'] ?? '') . ' ' . (string) ($posting['snippet'] ?? ''),
            $preferences['excluded_keywords'] ?? [],
        );
        if ($excludedMatch) {
            $penalties[] = [
                'score' => -$this->config->excludedKeywordPenalty,
                'weight' => 0,
                'reason' => lang('Jobs.score_reason_excluded'),
            ];
        }
        $allowedJobTypes = $this->stringList($preferences['job_types'] ?? []);
        $postingType = (string) ($posting['job_type'] ?? '');
        if ($postingType !== '' && $allowedJobTypes !== [] && ! in_array($postingType, $allowedJobTypes, true)) {
            $penalties[] = [
                'score' => -$this->config->incompatibleJobTypePenalty,
                'weight' => 0,
                'reason' => lang('Jobs.score_reason_job_type'),
            ];
        }
        if ($penalties !== []) {
            $penalty = array_sum(array_column($penalties, 'score'));
            $score = max(0, $score + $penalty);
            $breakdown['penalties'] = [
                'score' => (float) $penalty,
                'weight' => 0,
                'normalized_weight' => 0.0,
                'reason' => implode(' ', array_column($penalties, 'reason')),
            ];
        }

        $label = match (true) {
            $score >= $this->config->scoreThresholds['very_good'] => $this->config->scoreLabels['very_good'],
            $score >= $this->config->scoreThresholds['good'] => $this->config->scoreLabels['good'],
            $score >= $this->config->scoreThresholds['partial'] => $this->config->scoreLabels['partial'],
            default => $this->config->scoreLabels['low'],
        };
        $componentCount = count(array_filter(
            $breakdown,
            static fn (string $name): bool => $name !== 'penalties',
            ARRAY_FILTER_USE_KEY,
        ));
        $snippet = trim((string) ($posting['snippet'] ?? ''));
        $confidence = match (true) {
            mb_strlen($snippet, 'UTF-8') < $this->config->minimumSnippetLength
                || $componentCount < $this->config->confidenceMinimumComponents['medium'] => 'rendah',
            $componentCount >= $this->config->confidenceMinimumComponents['high'] => 'tinggi',
            default => 'sedang',
        };

        $reasons = [];
        if ($confidence === 'rendah') {
            $reasons[] = mb_strlen($snippet, 'UTF-8') < $this->config->minimumSnippetLength
                ? lang('Jobs.score_reason_confidence_snippet')
                : lang('Jobs.score_reason_confidence_components');
        }
        foreach ($penalties as $penalty) {
            $reasons[] = $penalty['reason'];
        }
        foreach ($breakdown as $name => $component) {
            if ($name !== 'penalties' && $component['reason'] !== '') {
                $reasons[] = $component['reason'];
            }
        }
        return new MatchResult(
            $score,
            $label,
            $confidence,
            $breakdown,
            $matchedSkills,
            $missingSkills,
            array_slice(array_values(array_unique($reasons)), 0, $this->config->maximumReasonCount),
            $excludedMatch,
            ($excludedMatch && $this->config->hideExcludedKeywordMatches)
                || ($this->config->hideLowScoreMatches && $score <= $this->config->lowMatchScoreMaximum),
        );
    }

    /**
     * @param list<string> $targetTitles
     * @param list<array{canonical_title: string, aliases: list<string>, job_family?: string, seniority_hint?: string|null}> $dictionary
     *
     * @return array{score: float, reason: string}|null
     */
    public function titleComponent(string $jobTitle, mixed $targetTitles, array $dictionary): ?array
    {
        $targets = $this->stringList($targetTitles);
        if ($targets === [] || trim($jobTitle) === '') {
            return null;
        }

        $jobMatch = $this->titleMatcher->match($jobTitle, $dictionary);
        $best = 0.0;
        $reason = 'Judul lowongan belum menunjukkan kemiripan dengan jabatan target Anda.';
        foreach ($targets as $target) {
            $targetMatch = $this->titleMatcher->match($target, $dictionary);
            if ($jobMatch !== null && $targetMatch !== null && $jobMatch['canonical_title'] === $targetMatch['canonical_title']) {
                return [
                    'score' => 100.0,
                    'reason' => lang('Jobs.score_reason_title_exact', [$jobMatch['canonical_title']]),
                ];
            }

            $tokenScore = $this->jaccard($jobTitle, $target) * 100;
            $familyScore = $jobMatch !== null
                && $targetMatch !== null
                && $jobMatch['job_family'] !== ''
                && $jobMatch['job_family'] === $targetMatch['job_family']
                    ? 100.0
                    : 0.0;
            if ($jobMatch !== null && $targetMatch !== null) {
                $parts = $this->config->titleSimilarityWeights;
                $sum = array_sum($parts);
                $score = $sum > 0
                    ? ($tokenScore * ($parts['token_jaccard'] ?? 0)
                        + $familyScore * ($parts['job_family'] ?? 0)) / $sum
                    : 0.0;
            } else {
                $score = $tokenScore;
            }

            if ($score > $best) {
                $best = $score;
                $reason = $familyScore > 0
                    ? lang('Jobs.score_reason_title_family')
                    : ($tokenScore > 0
                        ? lang('Jobs.score_reason_title_tokens')
                        : lang('Jobs.score_reason_title_none'));
            }
        }

        return ['score' => round($best, 2), 'reason' => $reason];
    }

    /**
     * @param array<string, array{weight: int, occurrences: int, category: string}> $cvSkills
     * @param list<array{canonical: string, aliases: list<string>, category?: string, weight?: int}> $dictionary
     *
     * @return array{component: array{score: float, reason: string}|null, matched: list<string>, missing: list<string>}
     */
    public function skillComponent(string $jobText, array $cvSkills, array $dictionary): array
    {
        $requested = $this->skillExtractor->extract($jobText, $dictionary);
        if ($requested === []) {
            return ['component' => null, 'matched' => [], 'missing' => []];
        }

        $cvSkillKeys = [];
        $cvSkillWeights = [];
        foreach ($cvSkills as $skill => $value) {
            $cvSkillKeys[SkillExtractor::normalize((string) $skill)] = true;
            $cvSkillWeights[SkillExtractor::normalize((string) $skill)] = max(
                1,
                (int) ($value['weight'] ?? 1),
            );
        }
        $totalDemand = 0;
        $matchedDemand = 0;
        $matched = [];
        $missing = [];
        foreach ($requested as $canonical => $skill) {
            $demandWeight = max(1, (int) $skill['weight']);
            $totalDemand += $demandWeight;
            if (isset($cvSkillKeys[SkillExtractor::normalize($canonical)])) {
                $matchedDemand += $cvSkillWeights[SkillExtractor::normalize($canonical)];
                $matched[] = $canonical;
            } else {
                $missing[] = $canonical;
            }
        }

        $score = $totalDemand > 0 ? 100 * $matchedDemand / $totalDemand : 0.0;
        if (count($requested) === 1 && $matched !== []) {
            $score = min($score, $this->config->singleSkillMaximumScore);
        }
        if ($matched !== []) {
            $score = max($score, min($this->config->minimumSkillComponentScore, 100));
        }
        $reason = $matched === []
            ? lang('Jobs.score_reason_skill_none')
            : lang('Jobs.score_reason_skill_matched', [implode(', ', $matched)]);

        return [
            'component' => ['score' => round($score, 2), 'reason' => $reason],
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    /**
     * @return array{score: float, reason: string}|null
     */
    public function seniorityComponent(?string $cvLevel, string $jobText): ?array
    {
        if ($cvLevel === null) {
            return null;
        }
        $jobLevel = $this->seniorityLevel($jobText);
        if ($jobLevel === null) {
            return null;
        }
        $cvIndex = $this->seniorityIndex($cvLevel);
        $jobIndex = $this->seniorityIndex($jobLevel);
        if ($cvIndex === null || $jobIndex === null) {
            return null;
        }
        if (in_array($cvLevel, ['intern', 'fresh_graduate'], true)
            && in_array($jobLevel, ['intern', 'fresh_graduate'], true)) {
            return [
                'score' => 100.0,
                'reason' => lang('Jobs.score_reason_seniority_entry'),
            ];
        }
        $distance = abs($cvIndex - $jobIndex);
        $value = $this->config->seniorityDistanceScores[$distance]
            ?? $this->config->seniorityDistanceScores['2+']
            ?? 0;

        return [
            'score' => (float) $value,
            'reason' => $distance === 0
                ? lang('Jobs.score_reason_seniority_same')
                : lang('Jobs.score_reason_seniority_distance', [$distance]),
        ];
    }

    /**
     * @param array<string, mixed> $preferences
     *
     * @return array{score: float, reason: string}|null
     */
    public function locationComponent(string $jobLocation, mixed $isRemote, array $preferences): ?array
    {
        $cities = $this->stringList($preferences['locations'] ?? []);
        $mode = (string) ($preferences['remote_mode'] ?? 'any');
        $remote = filter_var($isRemote, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($remote === null) {
            $remote = preg_match('/\b(?:remote|work from home|wfh|jarak jauh|hybrid)\b/iu', $jobLocation) === 1;
        }
        if ($remote && in_array($mode, ['any', 'remote', 'hybrid'], true)) {
            return ['score' => (float) $this->config->locationScores['remote'], 'reason' => lang('Jobs.score_reason_location_remote')];
        }
        if ($remote && $mode === 'onsite') {
            return ['score' => (float) $this->config->locationScores['remote_denied'], 'reason' => lang('Jobs.score_reason_location_remote_denied')];
        }
        if ($mode === 'remote') {
            return ['score' => (float) $this->config->locationScores['remote_denied'], 'reason' => lang('Jobs.score_reason_location_not_remote')];
        }
        if ($jobLocation === '' || $cities === []) {
            return null;
        }

        foreach ($cities as $city) {
            if (SkillExtractor::normalize($city) === SkillExtractor::normalize($jobLocation)) {
                return ['score' => (float) $this->config->locationScores['same_city'], 'reason' => lang('Jobs.score_reason_location_city')];
            }
            $area = $this->areaFor($city);
            if ($area !== null && $area === $this->areaFor($jobLocation)) {
                return ['score' => (float) $this->config->locationScores['same_area'], 'reason' => lang('Jobs.score_reason_location_area')];
            }
        }

        if ($mode === 'hybrid') {
            return ['score' => (float) $this->config->locationScores['other'], 'reason' => lang('Jobs.score_reason_location_hybrid')];
        }

        return ['score' => (float) $this->config->locationScores['other'], 'reason' => lang('Jobs.score_reason_location_other')];
    }

    /**
     * @param array<string, mixed> $posting
     *
     * @return array{score: float, reason: string}|null
     */
    public function freshnessComponent(array $posting, ?DateTimeImmutable $asOf = null): ?array
    {
        if (! is_string($posting['posted_at'] ?? null) || $posting['posted_at'] === '') {
            return null;
        }
        try {
            $postedAt = new DateTimeImmutable($posting['posted_at'], new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
        $today = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $ageDays = max(0, (int) $postedAt->diff($today)->format('%r%a'));
        $full = max(0, $this->config->freshnessFullScoreDays);
        $low = max($full + 1, $this->config->freshnessLowScoreDays);
        $score = $ageDays <= $full ? 100 : max(0, 100 * ($low - $ageDays) / ($low - $full));

        return [
            'score' => round($score, 2),
            'reason' => $ageDays === 0
                ? lang('Jobs.score_reason_freshness_today')
                : lang('Jobs.score_reason_freshness_age', [$ageDays]),
        ];
    }

    /**
     * @param array<string, mixed> $posting
     * @param array<string, mixed> $preferences
     *
     * @return array{score: float, reason: string}|null
     */
    public function salaryComponent(array $posting, array $preferences): ?array
    {
        $minimum = $preferences['min_salary_idr'] ?? null;
        $maximum = $posting['salary_max_idr'] ?? null;
        $jobMinimum = $posting['salary_min_idr'] ?? null;
        if (! is_numeric($minimum) || (int) $minimum <= 0 || (! is_numeric($maximum) && ! is_numeric($jobMinimum))) {
            return null;
        }
        $highestOffered = is_numeric($maximum) ? (int) $maximum : (int) $jobMinimum;
        $meets = $highestOffered >= (int) $minimum;
        $value = $this->config->salaryScores[$meets ? 'meets_minimum' : 'below_minimum'];

        return [
            'score' => (float) $value,
            'reason' => $meets
                ? lang('Jobs.score_reason_salary_meets')
                : lang('Jobs.score_reason_salary_below'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function normalizedWeights(): array
    {
        $expected = ['title', 'skill', 'seniority', 'location', 'freshness', 'salary'];
        $weights = [];
        foreach ($expected as $component) {
            $weight = $this->config->scoreWeights[$component] ?? 0;
            if (! is_int($weight) || $weight < 0) {
                throw new InvalidArgumentException('Bobot kecocokan harus berupa bilangan bulat nonnegatif.');
            }
            $weights[$component] = $weight;
        }
        if (array_sum($weights) === 0) {
            throw new InvalidArgumentException('Setidaknya satu bobot kecocokan harus lebih besar dari nol.');
        }

        return $weights;
    }

    /**
     * @param array<string, array{score: float, weight: int, reason: string}> $breakdown
     * @param array{score: float, reason: string}|null $component
     * @param array<string, int> $weights
     */
    private function addComponent(array &$breakdown, string $name, ?array $component, array $weights): void
    {
        $weight = $weights[$name] ?? 0;
        if ($component === null || $weight === 0) {
            return;
        }
        $breakdown[$name] = [
            'score' => max(0.0, min(100.0, $component['score'])),
            'weight' => $weight,
            'reason' => $component['reason'],
        ];
    }

    private function seniorityLevel(string $value): ?string
    {
        $normalized = SkillExtractor::normalize($value);
        foreach ($this->config->seniorityAliases as $alias => $level) {
            if (preg_match('/(?<![\pL\pN])' . preg_quote(SkillExtractor::normalize($alias), '/') . '(?![\pL\pN])/u', $normalized) === 1) {
                return $level;
            }
        }
        if (in_array($normalized, ['fresh graduate', 'fresh_graduate'], true)) {
            return 'fresh_graduate';
        }
        if (in_array($normalized, ['mid', 'senior', 'lead', 'intern', 'junior'], true)) {
            return $normalized;
        }

        return null;
    }

    private function seniorityIndex(string $level): ?int
    {
        $index = array_search($level, ['intern', 'fresh_graduate', 'junior', 'mid', 'senior', 'lead'], true);

        return $index === false ? null : $index;
    }

    private function jaccard(string $left, string $right): float
    {
        $stopwords = array_map(SkillExtractor::normalize(...), $this->config->stopwords);
        $tokens = static function (string $value) use ($stopwords): array {
            $tokens = explode(' ', SkillExtractor::normalize($value));

            return array_values(array_diff(array_unique($tokens), $stopwords));
        };
        $leftTokens = $tokens($left);
        $rightTokens = $tokens($right);
        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique([...$leftTokens, ...$rightTokens]));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function areaFor(string $location): ?string
    {
        $normalized = SkillExtractor::normalize($location);
        foreach ($this->config->locationAreas as $area => $cities) {
            foreach ($cities as $city) {
                if ($normalized === SkillExtractor::normalize($city)) {
                    return $area;
                }
            }
        }

        return null;
    }

    private function hasExcludedKeyword(string $text, mixed $keywords): bool
    {
        $normalizedText = ' ' . SkillExtractor::normalize($text) . ' ';
        foreach ($this->stringList($keywords) as $keyword) {
            $normalizedKeyword = SkillExtractor::normalize($keyword);
            if ($normalizedKeyword !== '' && str_contains($normalizedText, ' ' . $normalizedKeyword . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
