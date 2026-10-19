<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <nav class="mb-5 text-sm" aria-label="<?= esc(lang('Jobs.breadcrumb')) ?>">
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.title')) ?></a>
        <span aria-hidden="true"> / </span>
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-jobs-results', $cvId)) ?>"><?= esc(lang('Jobs.results_title')) ?></a>
        <span aria-hidden="true"> / </span>
        <span><?= esc(lang('Jobs.applications_title')) ?></span>
    </nav>

    <section class="max-w-4xl" aria-labelledby="application-list-heading">
        <h1 class="text-2xl font-semibold" id="application-list-heading"><?= esc(lang('Jobs.applications_title')) ?></h1>
        <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.application_tracking_intro')) ?></p>
        <?php if ($applications === []): ?>
            <div class="mt-4 rounded-md border border-neutral-200 bg-white p-5" role="status">
                <h2 class="font-semibold"><?= esc(lang('Jobs.applications_empty_title')) ?></h2>
                <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('Jobs.applications_empty')) ?></p>
            </div>
        <?php else: ?>
            <ul class="mt-4 space-y-3">
                <?php foreach ($applications as $application): ?>
                    <?php $isExpired = (bool) $application['saved_expired'] || ($application['title'] === null && $application['match_id'] === null); ?>
                    <li class="rounded-md border border-neutral-200 bg-white p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="font-semibold">
                                    <?php if (! $isExpired && $application['current_match_id'] !== null): ?>
                                        <a class="text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-jobs-detail', $cvId, $application['current_match_id'])) ?>"><?= esc((string) ($application['title'] ?? lang('Jobs.company_not_listed'))) ?></a>
                                    <?php else: ?>
                                        <?= esc(lang($application['saved_expired'] ? 'Jobs.expired_saved_job' : 'Jobs.expired_tracked_job')) ?>
                                    <?php endif; ?>
                                </h2>
                                <?php if (! $isExpired && $application['company']): ?>
                                    <p class="mt-1 text-sm text-neutral-700"><?= esc((string) $application['company']) ?></p>
                                <?php endif; ?>
                                <?php if ($isExpired): ?>
                                    <p class="mt-1 text-sm text-amber-900"><?= esc(lang('Jobs.expired_saved_notice')) ?></p>
                                <?php endif; ?>
                            </div>
                            <p class="inline-flex min-h-9 items-center rounded-md border border-neutral-400 px-3 text-sm font-medium"><?= esc(lang('Jobs.application_status_' . $application['application_status'])) ?></p>
                        </div>
                        <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.application_last_changed', [(string) $application['status_changed_at']])) ?></p>
                        <?php if (! empty($application['notes'])): ?>
                            <p class="mt-2 whitespace-pre-line text-sm text-neutral-700"><?= esc((string) $application['notes']) ?></p>
                        <?php endif; ?>
                        <?php if (! $isExpired && ! empty($application['source_name'])): ?>
                            <p class="mt-2 text-xs text-neutral-600"><?= esc(lang('Jobs.source')) ?>: <?= esc((string) $application['source_name']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?= $this->endSection() ?>
