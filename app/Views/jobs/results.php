<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <nav class="mb-5 text-sm" aria-label="<?= esc(lang('Jobs.breadcrumb')) ?>">
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.title')) ?></a>
        <span aria-hidden="true"> / </span>
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($preferencesUrl) ?>"><?= esc(lang('Jobs.preferences_title')) ?></a>
        <span aria-hidden="true"> / </span>
        <span><?= esc(lang('Jobs.results_title')) ?></span>
    </nav>

    <section aria-labelledby="results-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold" id="results-title"><?= esc(lang('Jobs.results_title')) ?></h1>
                <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.results_for_cv', [$cv->title])) ?></p>
                <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.score_snippet_note')) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="inline-flex min-h-11 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($preferencesUrl) ?>"><?= esc(lang('Jobs.edit_preferences')) ?></a>
                <a class="inline-flex min-h-11 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($applicationsUrl) ?>"><?= esc(lang('Jobs.applications_title')) ?></a>
                <a class="inline-flex min-h-11 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($insightsUrl) ?>"><?= esc(lang('Jobs.insights_title')) ?></a>
                <form action="<?= esc($refreshUrl) ?>" method="post" x-data="{ pending: false }" x-on:submit="pending = true" x-bind:aria-busy="pending.toString()">
                    <?= csrf_field() ?>
                    <button class="min-h-11 rounded-md bg-accent-800 px-4 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit" x-bind:disabled="pending">
                        <span x-show="!pending"><?= esc(lang('Jobs.refresh_search')) ?></span>
                        <span x-cloak x-show="pending"><?= esc(lang('Jobs.loading')) ?></span>
                    </button>
                    <span class="sr-only" role="status" aria-live="polite" x-text="pending ? <?= esc(json_encode(lang('Jobs.loading'), JSON_THROW_ON_ERROR), 'attr') ?> : ''"></span>
                </form>
            </div>
        </div>

        <form class="mt-5 grid gap-4 rounded-md border border-neutral-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3" action="<?= esc($resultsUrl) ?>" method="get">
            <label class="block text-sm font-medium" for="filter-location"><?= esc(lang('Jobs.filter_location')) ?>
                <input class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="filter-location" name="location" type="search" maxlength="120" value="<?= esc($filters['location']) ?>">
            </label>
            <label class="block text-sm font-medium" for="filter-type"><?= esc(lang('Jobs.filter_job_type')) ?>
                <select class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="filter-type" name="job_type">
                    <option value=""><?= esc(lang('Jobs.filter_any')) ?></option>
                    <?php foreach ($jobTypes as $type): ?>
                        <option value="<?= esc($type) ?>" <?= $filters['job_type'] === $type ? 'selected' : '' ?>><?= esc(lang('Jobs.type_' . $type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm font-medium" for="filter-remote"><?= esc(lang('Jobs.filter_work_mode')) ?>
                <select class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="filter-remote" name="remote">
                    <option value=""><?= esc(lang('Jobs.filter_any')) ?></option>
                    <option value="remote" <?= $filters['remote'] === 'remote' ? 'selected' : '' ?>><?= esc(lang('Jobs.remote_remote')) ?></option>
                    <option value="onsite" <?= $filters['remote'] === 'onsite' ? 'selected' : '' ?>><?= esc(lang('Jobs.remote_onsite')) ?></option>
                </select>
            </label>
            <label class="block text-sm font-medium" for="filter-status"><?= esc(lang('Jobs.filter_status')) ?>
                <select class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="filter-status" name="status">
                    <?php foreach (['active', 'saved', 'hidden', 'all'] as $status): ?>
                        <option value="<?= esc($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= esc(lang('Jobs.status_filter_' . $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm font-medium" for="filter-min-score"><?= esc(lang('Jobs.filter_min_score')) ?>
                <input class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="filter-min-score" name="min_score" type="number" min="0" max="100" step="1" value="<?= esc((string) $filters['min_score']) ?>">
            </label>
            <label class="block text-sm font-medium" for="filter-sort"><?= esc(lang('Jobs.sort_by')) ?>
                <select class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="filter-sort" name="sort">
                    <?php foreach (['relevance', 'recent', 'salary'] as $option): ?>
                        <option value="<?= esc($option) ?>" <?= $sort === $option ? 'selected' : '' ?>><?= esc(lang('Jobs.sort_' . $option)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                <button class="min-h-11 rounded-md bg-accent-800 px-4 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Jobs.apply_filters')) ?></button>
                <a class="inline-flex min-h-11 items-center rounded-md border border-neutral-300 px-4 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($resultsUrl) ?>"><?= esc(lang('Jobs.clear_filters')) ?></a>
            </div>
        </form>

        <p class="mt-4 text-sm text-neutral-700" role="status" aria-live="polite"><?= esc(lang('Jobs.result_count', [$total])) ?></p>

        <?php if ($items === []): ?>
            <div class="mt-3 rounded-md border border-neutral-200 bg-white p-5" role="status">
                <h2 class="font-semibold"><?= esc(lang('Jobs.no_results_title')) ?></h2>
                <p class="mt-1 text-sm text-neutral-700"><?= esc($hasFilters ? lang('Jobs.no_filtered_results') : lang('Jobs.no_results')) ?></p>
                <?php if (! $hasFilters): ?>
                    <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.results_empty_help')) ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="mt-3 space-y-3">
                <?php foreach ($items as $job): ?>
                    <?php
                    $labels = ['very_good', 'good', 'partial', 'low'];
                    $label = in_array($job['label'] ?? '', $labels, true) ? $job['label'] : 'low';
                    $detailUrl = url_to('cv-jobs-detail', $cvId, (string) $job['match_id']);
                    ?>
                    <li class="rounded-md border border-neutral-200 bg-white p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold">
                                    <a class="break-words text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($detailUrl) ?>"><?= esc((string) $job['title']) ?></a>
                                </h2>
                                <p class="mt-1 text-sm text-neutral-700">
                                    <?= esc((string) ($job['company'] ?: lang('Jobs.company_not_listed'))) ?>
                                    <?php if (! empty($job['location'])): ?>, <?= esc((string) $job['location']) ?><?php endif; ?>
                                </p>
                            </div>
                            <?php if ($job['score'] !== null): ?>
                                <?php $matchLabel = lang('Jobs.score_label_' . $label); ?>
                                <p class="inline-flex min-h-9 shrink-0 items-center rounded-md border border-accent-800 bg-accent-50 px-3 text-sm font-semibold text-accent-900" aria-label="<?= esc(lang('Jobs.match_score_label', [$job['score'], $matchLabel])) ?>">
                                    <?= esc(lang('Jobs.match_score', [$job['score'], $matchLabel])) ?>
                                </p>
                            <?php else: ?>
                                <p class="inline-flex min-h-9 shrink-0 items-center rounded-md border border-neutral-400 px-3 text-sm font-medium"><?= esc(lang('Jobs.score_pending')) ?></p>
                            <?php endif; ?>
                        </div>
                        <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-neutral-700">
                            <span><?= esc(lang('Jobs.confidence', [$job['confidence'] ?? 'rendah'])) ?></span>
                            <?php if ($job['status'] === 'saved'): ?><span class="font-medium"><?= esc(lang('Jobs.status_saved')) ?></span><?php endif; ?>
                            <?php if ($job['job_type']): ?><span><?= esc(lang('Jobs.type_' . $job['job_type'])) ?></span><?php endif; ?>
                            <?php if ($job['salary_text']): ?><span><?= esc((string) $job['salary_text']) ?></span><?php endif; ?>
                        </p>
                        <?php if ($job['snippet'] !== ''): ?>
                            <p class="mt-3 text-sm leading-6 text-neutral-800"><?= esc(mb_strimwidth((string) $job['snippet'], 0, 320, '...')) ?></p>
                        <?php endif; ?>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-neutral-600"><?= esc(lang('Jobs.source')) ?>: <?= esc((string) ($job['source_name'] ?? '')) ?></p>
                            <a class="min-h-10 font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($detailUrl) ?>"><?= esc(lang('Jobs.view_detail')) ?></a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php $pageCount = max(1, (int) ceil($total / $perPage)); ?>
        <?php if ($pageCount > 1): ?>
            <?php
            $query = [
                'status' => $filters['status'],
                'location' => $filters['location'],
                'job_type' => $filters['job_type'],
                'remote' => $filters['remote'],
                'min_score' => $filters['min_score'],
                'sort' => $sort,
            ];
            $pageUrl = static fn (int $number): string => $resultsUrl . '?' . http_build_query([...$query, 'page' => $number]);
            ?>
            <nav class="mt-5 flex items-center justify-between gap-3" aria-label="<?= esc(lang('Jobs.pagination')) ?>">
                <?php if ($page > 1): ?>
                    <a class="inline-flex min-h-11 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" rel="prev" href="<?= esc($pageUrl($page - 1)) ?>"><?= esc(lang('Jobs.previous_page')) ?></a>
                <?php else: ?><span></span><?php endif; ?>
                <span class="text-sm text-neutral-700"><?= esc(lang('Jobs.page_of', [$page, $pageCount])) ?></span>
                <?php if ($page < $pageCount): ?>
                    <a class="inline-flex min-h-11 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" rel="next" href="<?= esc($pageUrl($page + 1)) ?>"><?= esc(lang('Jobs.next_page')) ?></a>
                <?php else: ?><span></span><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?= $this->endSection() ?>
