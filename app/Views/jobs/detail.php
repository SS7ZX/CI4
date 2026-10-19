<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <nav class="mb-5 text-sm" aria-label="<?= esc(lang('Jobs.breadcrumb')) ?>">
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.title')) ?></a>
        <span aria-hidden="true"> / </span>
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($resultsUrl) ?>"><?= esc(lang('Jobs.results_title')) ?></a>
        <span aria-hidden="true"> / </span>
        <span><?= esc(lang('Jobs.detail_title')) ?></span>
    </nav>

    <?php
    $labels = ['very_good', 'good', 'partial', 'low'];
    $label = in_array($job['label'] ?? '', $labels, true) ? $job['label'] : 'low';
    $matchLabel = lang('Jobs.score_label_' . $label);
    $breakdown = json_decode((string) ($job['breakdown'] ?? '{}'), true);
    $breakdown = is_array($breakdown) ? $breakdown : [];
    $matchedSkills = json_decode((string) ($job['matched_skills'] ?? '[]'), true);
    $matchedSkills = is_array($matchedSkills) ? $matchedSkills : [];
    $missingSkills = json_decode((string) ($job['missing_skills'] ?? '[]'), true);
    $missingSkills = is_array($missingSkills) ? $missingSkills : [];
    $reasons = json_decode((string) ($job['reasons'] ?? '[]'), true);
    $reasons = is_array($reasons) ? $reasons : [];
    ?>
    <article class="max-w-4xl">
        <header class="rounded-md border border-neutral-200 bg-white p-5 sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="break-words text-2xl font-semibold"><?= esc((string) $job['title']) ?></h1>
                    <p class="mt-2 text-neutral-700">
                        <?= esc((string) ($job['company'] ?: lang('Jobs.company_not_listed'))) ?>
                        <?php if (! empty($job['location'])): ?>, <?= esc((string) $job['location']) ?><?php endif; ?>
                    </p>
                    <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-neutral-700">
                        <?php if ($job['job_type']): ?><span><?= esc(lang('Jobs.type_' . $job['job_type'])) ?></span><?php endif; ?>
                        <?php if ($job['salary_text']): ?><span><?= esc((string) $job['salary_text']) ?></span><?php endif; ?>
                        <?php if ($job['posted_at']): ?><span><?= esc(lang('Jobs.posted_at', [$job['posted_at']])) ?></span><?php endif; ?>
                    </p>
                </div>
                <?php if ($job['score'] !== null): ?>
                    <p class="inline-flex min-h-10 shrink-0 items-center rounded-md border border-accent-800 bg-accent-50 px-3 text-sm font-semibold text-accent-900" aria-label="<?= esc(lang('Jobs.match_score_label', [$job['score'], $matchLabel])) ?>">
                        <?= esc(lang('Jobs.match_score', [$job['score'], $matchLabel])) ?>
                    </p>
                <?php else: ?>
                    <p class="inline-flex min-h-10 shrink-0 items-center rounded-md border border-neutral-400 px-3 text-sm font-medium"><?= esc(lang('Jobs.score_pending')) ?></p>
                <?php endif; ?>
            </div>
            <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.confidence', [$job['confidence'] ?? 'rendah'])) ?></p>
            <div class="mt-4 flex flex-wrap gap-2">
                <?php if ($externalUrl !== null): ?>
                    <a class="inline-flex min-h-11 items-center rounded-md bg-accent-800 px-4 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($externalUrl) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= esc(lang('Jobs.open_original')) ?></a>
                <?php else: ?>
                    <p class="text-sm text-neutral-700"><?= esc(lang('Jobs.original_link_unavailable')) ?></p>
                <?php endif; ?>
                <?php if ($job['status'] === 'hidden'): ?>
                    <form action="<?= esc($restoreUrl) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="min-h-11 rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Jobs.restore_job')) ?></button>
                    </form>
                <?php else: ?>
                    <form action="<?= esc($saveUrl) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="min-h-11 rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc($job['status'] === 'saved' ? lang('Jobs.unsave_job') : lang('Jobs.save_job')) ?></button>
                    </form>
                    <form action="<?= esc($hideUrl) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="min-h-11 rounded-md border border-red-700 bg-white px-4 text-sm font-medium text-red-800 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="submit"><?= esc(lang('Jobs.hide_job')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="mt-4 text-sm text-neutral-700"><?= esc(lang('Jobs.source')) ?>: <?= esc((string) ($job['source_name'] ?? $job['provider'] ?? '')) ?></p>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.score_snippet_note')) ?></p>
        </header>

        <section class="mt-4 rounded-md border border-neutral-200 bg-white p-5 sm:p-7" aria-labelledby="application-tracking-heading">
            <h2 class="text-lg font-semibold" id="application-tracking-heading"><?= esc(lang('Jobs.application_tracking_title')) ?></h2>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.application_tracking_intro')) ?></p>
            <form class="mt-4 space-y-4" action="<?= esc($applicationUrl) ?>" method="post">
                <?= csrf_field() ?>
                <label class="block text-sm font-medium" for="application-status"><?= esc(lang('Jobs.application_status')) ?>
                    <select class="mt-1 min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="application-status" name="application_status">
                        <?php foreach ($applicationStatuses as $status): ?>
                            <option value="<?= esc($status) ?>" <?= $applicationTracking['application_status'] === $status ? 'selected' : '' ?>><?= esc(lang('Jobs.application_status_' . $status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm font-medium" for="application-notes"><?= esc(lang('Jobs.application_notes')) ?>
                    <textarea class="mt-1 min-h-28 w-full rounded-md border border-neutral-400 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="application-notes" name="application_notes" maxlength="2000"><?= esc((string) ($applicationTracking['notes'] ?? '')) ?></textarea>
                </label>
                <button class="min-h-11 rounded-md bg-accent-800 px-4 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Jobs.update_application')) ?></button>
            </form>
            <?php if (! empty($applicationTracking['status_changed_at'])): ?>
                <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.application_last_changed', [(string) $applicationTracking['status_changed_at']])) ?></p>
            <?php endif; ?>
            <?php if ($applicationEvents !== []): ?>
                <h3 class="mt-5 font-semibold"><?= esc(lang('Jobs.application_history')) ?></h3>
                <ol class="mt-2 space-y-2 border-l border-neutral-300 pl-4">
                    <?php foreach ($applicationEvents as $event): ?>
                        <li class="text-sm">
                            <p class="font-medium"><?= esc(lang('Jobs.application_status_' . $event['application_status'])) ?>, <?= esc((string) $event['changed_at']) ?></p>
                            <?php if (! empty($event['notes'])): ?><p class="mt-1 text-neutral-700"><?= esc((string) $event['notes']) ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <a class="mt-4 inline-flex min-h-10 items-center font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-jobs-applications', $cvId)) ?>"><?= esc(lang('Jobs.view_application_tracker')) ?></a>
        </section>

        <section class="mt-4 rounded-md border border-neutral-200 bg-white p-5 sm:p-7" aria-labelledby="score-breakdown-heading">
            <h2 class="text-lg font-semibold" id="score-breakdown-heading"><?= esc(lang('Jobs.score_details')) ?></h2>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.score_weight_note')) ?></p>
            <?php if ($breakdown === []): ?>
                <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.score_detail_unavailable')) ?></p>
            <?php else: ?>
                <dl class="mt-4 space-y-3">
                    <?php foreach ($breakdown as $component => $detail): ?>
                        <?php if ($component === 'penalties' || ! is_array($detail)): continue; endif; ?>
                        <?php if (! in_array($component, ['title', 'skill', 'seniority', 'location', 'freshness', 'salary'], true)): continue; endif; ?>
                        <div class="rounded-md border border-neutral-200 p-4">
                            <dt class="font-semibold"><?= esc(lang('Jobs.score_component_' . $component)) ?>: <?= esc((string) ($detail['score'] ?? 0)) ?>/100</dt>
                            <dd class="mt-1 text-sm text-neutral-700"><?= esc((string) ($detail['reason'] ?? '')) ?> <?= esc(lang('Jobs.component_weight', [$detail['weight'] ?? 0, $detail['normalized_weight'] ?? 0])) ?></dd>
                        </div>
                    <?php endforeach; ?>
                    <?php if (isset($breakdown['penalties']) && is_array($breakdown['penalties'])): ?>
                        <div class="rounded-md border border-amber-700 bg-amber-50 p-4 text-amber-900">
                            <dt class="font-semibold"><?= esc(lang('Jobs.score_penalty', [$breakdown['penalties']['score'] ?? 0])) ?></dt>
                            <dd class="mt-1 text-sm"><?= esc((string) ($breakdown['penalties']['reason'] ?? '')) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
            <?php if (! empty($job['excluded_keyword_match'])): ?>
                <p class="mt-3 rounded-md border border-amber-700 bg-amber-50 p-3 text-sm text-amber-900"><?= esc(lang('Jobs.excluded_keyword_notice')) ?></p>
            <?php endif; ?>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <section aria-labelledby="matched-skills-heading">
                    <h3 class="font-semibold" id="matched-skills-heading"><?= esc(lang('Jobs.matched_skills')) ?></h3>
                    <?php if ($matchedSkills === []): ?>
                        <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.no_matched_skills')) ?></p>
                    <?php else: ?>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-neutral-800">
                            <?php foreach ($matchedSkills as $skill): ?><li><?= esc((string) $skill) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
                <section aria-labelledby="missing-skills-heading">
                    <h3 class="font-semibold" id="missing-skills-heading"><?= esc(lang('Jobs.missing_skills')) ?></h3>
                    <?php if ($missingSkills === []): ?>
                        <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.no_missing_skills')) ?></p>
                    <?php else: ?>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-neutral-800">
                            <?php foreach ($missingSkills as $skill): ?><li><?= esc((string) $skill) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
            <?php if ($reasons !== []): ?>
                <section class="mt-5" aria-labelledby="score-reasons-heading">
                    <h3 class="font-semibold" id="score-reasons-heading"><?= esc(lang('Jobs.score_reasons_title')) ?></h3>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-neutral-700">
                        <?php foreach ($reasons as $reason): ?><li><?= esc((string) $reason) ?></li><?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </section>

        <section class="mt-4 rounded-md border border-neutral-200 bg-white p-5 sm:p-7" aria-labelledby="keyword-check-heading">
            <h2 class="text-lg font-semibold" id="keyword-check-heading"><?= esc(lang('Jobs.keyword_check_title')) ?></h2>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.keyword_check_intro')) ?></p>
            <?php if ($keywordChecks === []): ?>
                <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.keyword_check_empty')) ?></p>
            <?php else: ?>
                <ul class="mt-3 space-y-3">
                    <?php foreach ($keywordChecks as $check): ?>
                        <li class="rounded-md border border-neutral-200 p-4">
                            <p class="font-semibold"><?= esc($check['keyword']) ?>: <?= esc(lang($check['present'] ? 'Jobs.keyword_present' : 'Jobs.keyword_missing')) ?></p>
                            <?php if (! $check['present']): ?>
                                <?php $sectionLabels = array_map(static fn (string $section): string => lang('Jobs.cv_section_' . $section), $check['sections']); ?>
                                <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.keyword_section_suggestions', [implode(', ', $sectionLabels)])) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="mt-3 rounded-md border border-amber-700 bg-amber-50 p-3 text-sm text-amber-900"><?= esc(lang('Jobs.keyword_ethics_notice')) ?></p>
        </section>

        <section class="mt-4 rounded-md border border-neutral-200 bg-white p-5 sm:p-7" aria-labelledby="snippet-heading">
            <h2 class="text-lg font-semibold" id="snippet-heading"><?= esc(lang('Jobs.job_excerpt')) ?></h2>
            <?php if ((string) $job['snippet'] === ''): ?>
                <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.no_snippet')) ?></p>
            <?php else: ?>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-800"><?= esc((string) $job['snippet']) ?></p>
            <?php endif; ?>
            <p class="mt-4 text-sm text-neutral-700"><?= esc(lang('Jobs.source_attribution', [(string) ($job['source_name'] ?? $job['provider'] ?? '')])) ?></p>
        </section>

        <a class="mt-5 inline-flex min-h-11 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc($resultsUrl) ?>"><?= esc(lang('Jobs.back_to_results')) ?></a>
    </article>
<?= $this->endSection() ?>
