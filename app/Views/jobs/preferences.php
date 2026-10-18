<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <nav class="mb-5 text-sm" aria-label="<?= esc(lang('Jobs.breadcrumb')) ?>">
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.title')) ?></a>
        <span aria-hidden="true"> / </span>
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-jobs', $cvId)) ?>"><?= esc(lang('Jobs.find_jobs')) ?></a>
        <span aria-hidden="true"> / </span>
        <span><?= esc(lang('Jobs.preferences_title')) ?></span>
    </nav>

    <section class="max-w-3xl">
        <h1 class="text-2xl font-semibold"><?= esc(lang('Jobs.preferences_title')) ?></h1>
        <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.preferences_intro', [$cv->title])) ?></p>
        <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.search_intro')) ?></p>
        <?php if (session('warning')): ?>
            <p class="mt-3 rounded-md border border-amber-700 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status"><?= esc(session('warning')) ?></p>
        <?php endif; ?>

        <?php if (! $profile->isReady()): ?>
            <div class="mt-4 rounded-md border border-amber-700 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                <p class="font-semibold"><?= esc(lang('Jobs.profile_incomplete')) ?></p>
                <?php $missingLabels = array_map(static fn (string $section): string => lang('Jobs.section_' . $section), $profile->missingSections); ?>
                <p class="mt-1"><?= esc(lang('Jobs.missing_sections', [implode(', ', $missingLabels)])) ?></p>
            </div>
        <?php endif; ?>

        <section class="mt-4 rounded-md border border-neutral-200 bg-white p-5" aria-labelledby="extracted-profile-heading">
            <h2 class="text-base font-semibold" id="extracted-profile-heading"><?= esc(lang('Jobs.extracted_profile')) ?></h2>
            <p class="mt-2 text-sm text-neutral-700">
                <?= esc(lang('Jobs.extracted_seniority')) ?>:
                <?= esc(lang('Jobs.seniority_' . $profile->seniority)) ?>,
                <?= esc(lang('Jobs.extracted_experience', [number_format($profile->experienceYears, 1, ',', '.')])); ?>
            </p>
            <?php $titleAliases = array_values(array_diff($profile->titleAliases, $profile->targetTitles)); ?>
            <?php if ($titleAliases !== []): ?>
                <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.title_alias_suggestions', [implode(', ', $titleAliases)])) ?></p>
            <?php endif; ?>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.extracted_skills')) ?></p>
            <?php if ($profile->skills === []): ?>
                <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.no_extracted_skills')) ?></p>
            <?php else: ?>
                <ul class="mt-1 flex flex-wrap gap-2">
                    <?php foreach (array_keys($profile->skills) as $skill): ?>
                        <li class="rounded-md border border-neutral-300 px-2 py-1 text-sm"><?= esc($skill) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if (is_array(session('errors'))): ?>
            <ul class="mt-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?php foreach (session('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php
        $old = [
            'target_titles' => old('target_titles'),
            'locations' => old('locations'),
            'excluded_keywords' => old('excluded_keywords'),
            'job_types' => old('job_types'),
            'remote_mode' => old('remote_mode'),
            'min_salary_idr' => old('min_salary_idr'),
            'experience_level_override' => old('experience_level_override'),
        ];
        $targetTitles = is_array($old['target_titles'] ?? null) ? $old['target_titles'] : $preferences['target_titles'];
        $locations = is_array($old['locations'] ?? null) ? $old['locations'] : $preferences['locations'];
        $excludedKeywords = is_array($old['excluded_keywords'] ?? null) ? $old['excluded_keywords'] : $preferences['excluded_keywords'];
        $jobTypes = is_array($old['job_types'] ?? null) ? $old['job_types'] : $preferences['job_types'];
        ?>
        <form class="mt-5 space-y-5 rounded-md border border-neutral-200 bg-white p-5 sm:p-7" action="<?= esc($saveUrl) ?>" method="post">
            <?= csrf_field() ?>

            <fieldset>
                <legend class="text-sm font-semibold"><?= esc(lang('Jobs.target_titles')) ?></legend>
                <p class="mb-2 mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.target_titles_help')) ?></p>
                <datalist id="known-job-titles">
                    <?php foreach ($titleSuggestions as $suggestion): ?>
                        <option value="<?= esc($suggestion) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?php foreach (array_slice(array_pad($targetTitles, max(2, count($targetTitles)), ''), 0, 10) as $index => $value): ?>
                    <label class="mb-2 block text-sm font-medium" for="target-title-<?= esc((string) $index) ?>"><?= esc(lang('Jobs.target_title_number', [$index + 1])) ?>
                        <input class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="target-title-<?= esc((string) $index) ?>" name="target_titles[]" type="text" maxlength="160" list="known-job-titles" value="<?= esc((string) $value) ?>">
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <fieldset>
                <legend class="text-sm font-semibold"><?= esc(lang('Jobs.locations')) ?></legend>
                <p class="mb-2 mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.locations_help')) ?></p>
                <?php foreach (array_slice(array_pad($locations, max(2, count($locations)), ''), 0, 10) as $index => $value): ?>
                    <label class="mb-2 block text-sm font-medium" for="location-<?= esc((string) $index) ?>"><?= esc(lang('Jobs.location_number', [$index + 1])) ?>
                        <input class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="location-<?= esc((string) $index) ?>" name="locations[]" type="text" maxlength="120" value="<?= esc((string) $value) ?>">
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <label class="block text-sm font-semibold" for="remote-mode"><?= esc(lang('Jobs.remote_mode')) ?>
                <select class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="remote-mode" name="remote_mode">
                    <?php foreach (['any', 'remote', 'hybrid', 'onsite'] as $mode): ?>
                        <option value="<?= esc($mode) ?>" <?= ($old['remote_mode'] ?? $preferences['remote_mode']) === $mode ? 'selected' : '' ?>><?= esc(lang('Jobs.remote_' . $mode)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset>
                <legend class="text-sm font-semibold"><?= esc(lang('Jobs.job_types')) ?></legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <?php foreach (['full_time', 'part_time', 'contract', 'internship', 'freelance'] as $type): ?>
                        <label class="flex min-h-10 items-center gap-2 text-sm">
                            <input class="size-4 accent-accent-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="checkbox" name="job_types[]" value="<?= esc($type) ?>" <?= in_array($type, $jobTypes, true) ? 'checked' : '' ?>>
                            <?= esc(lang('Jobs.type_' . $type)) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <label class="block text-sm font-semibold" for="min-salary"><?= esc(lang('Jobs.min_salary')) ?>
                <input class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="min-salary" name="min_salary_idr" type="number" min="0" max="1000000000" step="100000" value="<?= esc((string) ($old['min_salary_idr'] ?? $preferences['min_salary_idr'] ?? '')) ?>">
            </label>

            <fieldset>
                <legend class="text-sm font-semibold"><?= esc(lang('Jobs.excluded_keywords')) ?></legend>
                <p class="mb-2 mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.excluded_keywords_help')) ?></p>
                <?php foreach (array_slice(array_pad($excludedKeywords, max(2, count($excludedKeywords)), ''), 0, 20) as $index => $value): ?>
                    <label class="mb-2 block text-sm font-medium" for="excluded-<?= esc((string) $index) ?>"><?= esc(lang('Jobs.excluded_keyword_number', [$index + 1])) ?>
                        <input class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="excluded-<?= esc((string) $index) ?>" name="excluded_keywords[]" type="text" maxlength="80" value="<?= esc((string) $value) ?>">
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <label class="block text-sm font-semibold" for="seniority"><?= esc(lang('Jobs.seniority_override')) ?>
                <select class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="seniority" name="experience_level_override">
                    <?php foreach (['', 'intern', 'fresh_graduate', 'junior', 'mid', 'senior', 'lead'] as $level): ?>
                        <option value="<?= esc($level) ?>" <?= ($old['experience_level_override'] ?? $preferences['experience_level_override']) === $level ? 'selected' : '' ?>><?= esc(lang($level === '' ? 'Jobs.seniority_auto' : 'Jobs.seniority_' . $level)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button class="min-h-11 rounded-md bg-accent-800 px-4 font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Jobs.save_preferences')) ?></button>
        </form>

        <section class="mt-6 rounded-md border border-neutral-200 bg-white p-5" aria-labelledby="job-results-heading">
            <h2 class="text-xl font-semibold" id="job-results-heading"><?= esc(lang('Jobs.results_title')) ?></h2>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.results_intro')) ?></p>
            <a class="mt-4 inline-flex min-h-11 items-center rounded-md bg-accent-800 px-4 font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($resultsUrl) ?>"><?= esc(lang('Jobs.browse_results')) ?></a>
        </section>

        <form class="mt-5" action="<?= esc($revokeUrl) ?>" method="post" x-on:submit.prevent="window.confirm(<?= esc(json_encode(lang('Jobs.confirm_revoke'), JSON_THROW_ON_ERROR), 'attr') ?>) && $el.submit()">
            <?= csrf_field() ?>
            <button class="min-h-11 rounded-md border border-red-700 px-4 text-sm font-medium text-red-800 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="submit"><?= esc(lang('Jobs.revoke_consent')) ?></button>
        </form>
    </section>
<?= $this->endSection() ?>
