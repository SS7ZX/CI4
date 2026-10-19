<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\JobConsentRequiredException;
use App\Services\JobPreferenceService;
use App\Services\JobPreferenceValidationException;
use App\Libraries\Jobs\JobIngestService;
use App\Libraries\Jobs\JobProviderException;
use App\Libraries\Jobs\JobSearchLimitException;
use App\Repositories\JobDictionaryRepository;
use App\Repositories\JobSearchRepository;
use App\Services\KeywordCheckService;
use App\Services\SkillGapService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;

final class Jobs extends BaseController
{
    public function index(string $cvId): string
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            return view('jobs/consent', [
                'title' => lang('Jobs.consent_title'),
                'cv'    => $workflow['cv'],
                'cvId'  => $cvId,
                'consentUrl' => url_to('cv-jobs-consent', $cvId),
            ]);
        }

        return $this->renderPreferences($cvId, $workflow);
    }

    public function preferences(string $cvId): string|RedirectResponse
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        }

        return $this->renderPreferences($cvId, $workflow);
    }

    public function results(string $cvId): string|RedirectResponse
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        }

        $requestFilters = $this->request->getGet();
        $allowedStatuses = ['active', 'all', 'saved', 'hidden'];
        $allowedTypes = ['full_time', 'part_time', 'contract', 'internship', 'freelance'];
        $status = is_string($requestFilters['status'] ?? null) && in_array($requestFilters['status'], $allowedStatuses, true)
            ? $requestFilters['status']
            : 'active';
        $jobType = is_string($requestFilters['job_type'] ?? null) && in_array($requestFilters['job_type'], $allowedTypes, true)
            ? $requestFilters['job_type']
            : '';
        $remote = is_string($requestFilters['remote'] ?? null) && in_array($requestFilters['remote'], ['remote', 'onsite'], true)
            ? $requestFilters['remote']
            : '';
        $sort = is_string($requestFilters['sort'] ?? null) && in_array($requestFilters['sort'], ['relevance', 'recent', 'salary'], true)
            ? $requestFilters['sort']
            : 'relevance';
        $location = is_string($requestFilters['location'] ?? null)
            ? mb_substr(trim($requestFilters['location']), 0, 120)
            : '';
        $minimumScore = filter_var($requestFilters['min_score'] ?? 0, FILTER_VALIDATE_INT);
        $page = filter_var($requestFilters['page'] ?? 1, FILTER_VALIDATE_INT);
        $filters = [
            'status' => $status,
            'job_type' => $jobType,
            'remote' => $remote,
            'location' => $location,
            'min_score' => is_int($minimumScore) ? max(0, min(100, $minimumScore)) : 0,
        ];
        $resultPage = (new JobSearchRepository())->paginatedMatchesForCv(
            $cvId,
            $filters,
            $sort,
            is_int($page) ? max(1, $page) : 1,
        );

        return view('jobs/results', [
            'title' => lang('Jobs.results_title'),
            'cv' => $workflow['cv'],
            'cvId' => $cvId,
            'items' => $resultPage['items'],
            'total' => $resultPage['total'],
            'page' => $resultPage['page'],
            'perPage' => $resultPage['perPage'],
            'filters' => $filters,
            'sort' => $sort,
            'hasFilters' => $jobType !== ''
                || $remote !== ''
                || $location !== ''
                || $filters['min_score'] > 0
                || $status !== 'active'
                || $sort !== 'relevance',
            'jobTypes' => $allowedTypes,
            'preferencesUrl' => url_to('cv-jobs-preferences', $cvId),
            'applicationsUrl' => url_to('cv-jobs-applications', $cvId),
            'insightsUrl' => url_to('cv-jobs-insights', $cvId),
            'refreshUrl' => url_to('cv-jobs-refresh', $cvId),
            'resultsUrl' => url_to('cv-jobs-results', $cvId),
        ]);
    }

    public function detail(string $cvId, string $matchId): string
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            throw PageNotFoundException::forPageNotFound();
        }
        $job = (new JobSearchRepository())->matchForCv($cvId, $matchId);
        if ($job === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $repository = new JobSearchRepository();
        $tracking = $repository->applicationTrackingForMatch($cvId, $matchId);
        $missingSkills = json_decode((string) ($job['missing_skills'] ?? '[]'), true);
        $missingSkills = is_array($missingSkills) ? array_values(array_filter($missingSkills, 'is_string')) : [];
        $keywordChecks = (new KeywordCheckService())->check(
            $workflow['cvData'],
            $missingSkills,
            (new JobDictionaryRepository())->skills(),
        );

        return view('jobs/detail', [
            'title' => lang('Jobs.detail_title'),
            'cv' => $workflow['cv'],
            'cvId' => $cvId,
            'job' => $job,
            'resultsUrl' => url_to('cv-jobs-results', $cvId),
            'saveUrl' => url_to('cv-jobs-save', $cvId, $matchId),
            'hideUrl' => url_to('cv-jobs-hide', $cvId, $matchId),
            'restoreUrl' => url_to('cv-jobs-restore', $cvId, $matchId),
            'applicationUrl' => url_to('cv-jobs-application-update', $cvId, $matchId),
            'applicationTracking' => $tracking,
            'applicationEvents' => $repository->applicationEventsForTracker(
                is_string($tracking['id'] ?? null) ? $tracking['id'] : null,
            ),
            'applicationStatuses' => ['not_applied', 'applied', 'interviewing', 'offer', 'rejected', 'withdrawn'],
            'keywordChecks' => $keywordChecks,
            'externalUrl' => $this->safeExternalUrl((string) ($job['link'] ?? '')),
        ]);
    }

    public function updateApplication(string $cvId, string $matchId): RedirectResponse
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        }

        $status = $this->request->getPost('application_status');
        $notes = $this->request->getPost('application_notes');
        $allowedStatuses = ['not_applied', 'applied', 'interviewing', 'offer', 'rejected', 'withdrawn'];
        if (
            ! is_string($status)
            || ! in_array($status, $allowedStatuses, true)
            || ! is_string($notes)
            || mb_strlen($notes, 'UTF-8') > 2000
        ) {
            return redirect()->route('cv-jobs-detail', [$cvId, $matchId])
                ->with('error', lang('Jobs.application_validation_error'));
        }

        if (! (new JobSearchRepository())->updateApplicationTracking($cvId, $matchId, $status, trim($notes))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return redirect()->route('cv-jobs-detail', [$cvId, $matchId])
            ->with('success', lang('Jobs.application_updated'));
    }

    public function applications(string $cvId): string|RedirectResponse
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        }

        return view('jobs/applications', [
            'title' => lang('Jobs.applications_title'),
            'cv' => $workflow['cv'],
            'cvId' => $cvId,
            'applications' => (new JobSearchRepository())->trackedApplicationsForCv($cvId),
        ]);
    }

    public function insights(string $cvId): string|RedirectResponse
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        }

        $insights = (new SkillGapService())->analyze(
            $workflow['profile'],
            (new JobSearchRepository())->matchesForCv($cvId),
            (new JobDictionaryRepository())->skills(),
        );

        return view('jobs/insights', [
            'title' => lang('Jobs.insights_title'),
            'cv' => $workflow['cv'],
            'cvId' => $cvId,
            'insights' => $insights,
        ]);
    }

    public function saveMatch(string $cvId, string $matchId): RedirectResponse
    {
        return $this->changeMatchStatus($cvId, $matchId, 'toggle_saved');
    }

    public function hideMatch(string $cvId, string $matchId): RedirectResponse
    {
        return $this->changeMatchStatus($cvId, $matchId, 'hidden');
    }

    public function restoreMatch(string $cvId, string $matchId): RedirectResponse
    {
        return $this->changeMatchStatus($cvId, $matchId, 'new');
    }

    public function grantConsent(string $cvId): RedirectResponse
    {
        if ($this->request->getPost('consent') !== '1') {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        }

        $this->service()->grantConsent($this->currentUser()->id, $cvId);

        return redirect()->route('cv-jobs-preferences', [$cvId])->with('success', lang('Jobs.consent_saved'));
    }

    public function savePreferences(string $cvId): RedirectResponse
    {
        try {
            $this->service()->savePreferences(
                $this->currentUser()->id,
                $cvId,
                $this->request->getPost(),
            );
        } catch (JobConsentRequiredException) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        } catch (JobPreferenceValidationException $exception) {
            return redirect()->route('cv-jobs-preferences', [$cvId])
                ->withInput()
                ->with('errors', $exception->errors());
        } catch (\RuntimeException) {
            return redirect()->route('cv-jobs-preferences', [$cvId])
                ->with('warning', lang('Jobs.score_recompute_failed'));
        }

        return redirect()->route('cv-jobs-preferences', [$cvId])->with('success', lang('Jobs.preferences_saved'));
    }

    public function refresh(string $cvId): RedirectResponse
    {
        try {
            $result = (new JobIngestService())->refresh($this->currentUser()->id, $cvId);
        } catch (JobConsentRequiredException) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        } catch (JobSearchLimitException) {
            return redirect()->route('cv-jobs-results', [$cvId])->with('error', lang('Jobs.search_limit'));
        } catch (JobProviderException) {
            return redirect()->route('cv-jobs-results', [$cvId])->with('error', lang('Jobs.provider_unavailable'));
        }

        $response = redirect()->route('cv-jobs-results', [$cvId])->with(
            'success',
            lang('Jobs.search_complete', [count($result['postings'])]),
        );
        if ($result['warnings'] !== []) {
            $response->with('warning', implode(' ', $result['warnings']));
        }

        return $response;
    }

    public function revokeConsent(string $cvId): RedirectResponse
    {
        $this->service()->revokeConsent($this->currentUser()->id, $cvId);

        return redirect()->route('cv-jobs', [$cvId])->with('success', lang('Jobs.consent_revoked'));
    }

    /**
     * @param array<string, mixed> $workflow
     */
    private function renderPreferences(string $cvId, array $workflow): string
    {
        return view('jobs/preferences', [
            'title'       => lang('Jobs.preferences_title'),
            'cv'          => $workflow['cv'],
            'cvId'        => $cvId,
            'profile'     => $workflow['profile'],
            'preferences' => $workflow['preference'] ?? $workflow['suggestedPreferences'],
            'titleSuggestions' => $workflow['titleSuggestions'],
            'saveUrl'     => url_to('cv-jobs-preferences-save', $cvId),
            'revokeUrl'   => url_to('cv-jobs-consent-revoke-form', $cvId),
            'refreshUrl'  => url_to('cv-jobs-refresh', $cvId),
            'resultsUrl'  => url_to('cv-jobs-results', $cvId),
        ]);
    }

    private function changeMatchStatus(string $cvId, string $matchId, string $status): RedirectResponse
    {
        $workflow = $this->service()->workflow($this->currentUser()->id, $cvId);
        if (! $workflow['hasConsent']) {
            return redirect()->route('cv-jobs', [$cvId])->with('error', lang('Jobs.consent_required'));
        }

        $repository = new JobSearchRepository();
        $job = $repository->matchForCv($cvId, $matchId);
        if ($job === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($status === 'toggle_saved') {
            $status = $job['status'] === 'saved' ? 'new' : 'saved';
        }
        if (! $repository->updateMatchStatus($cvId, $matchId, $status)) {
            throw new \RuntimeException('Status hasil lowongan tidak dapat diperbarui.');
        }

        $message = match ($status) {
            'saved' => lang('Jobs.saved'),
            'hidden' => lang('Jobs.hidden'),
            'new' => lang('Jobs.unsaved'),
            default => lang('Jobs.restored'),
        };

        return redirect()->route('cv-jobs-detail', [$cvId, $matchId])->with('success', $message);
    }

    private function safeExternalUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            ? $url
            : null;
    }

    private function service(): JobPreferenceService
    {
        return new JobPreferenceService();
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }
}
