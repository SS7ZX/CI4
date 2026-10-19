<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <nav class="mb-5 text-sm" aria-label="<?= esc(lang('Jobs.breadcrumb')) ?>">
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.title')) ?></a>
        <span aria-hidden="true"> / </span>
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-jobs-results', $cvId)) ?>"><?= esc(lang('Jobs.results_title')) ?></a>
        <span aria-hidden="true"> / </span>
        <span><?= esc(lang('Jobs.insights_title')) ?></span>
    </nav>

    <section class="max-w-4xl" aria-labelledby="insights-heading">
        <h1 class="text-2xl font-semibold" id="insights-heading"><?= esc(lang('Jobs.insights_title')) ?></h1>
        <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.insights_intro')) ?></p>

        <section class="mt-4 rounded-md border border-neutral-200 bg-white p-5 sm:p-7" aria-labelledby="skill-gap-heading">
            <h2 class="text-lg font-semibold" id="skill-gap-heading"><?= esc(lang('Jobs.skill_gap_title')) ?></h2>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.skill_gap_method')) ?></p>
            <?php if ($insights['skills'] === []): ?>
                <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.skill_gap_empty')) ?></p>
            <?php else: ?>
                <ul class="mt-3 divide-y divide-neutral-200">
                    <?php foreach ($insights['skills'] as $skill): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <span class="font-medium"><?= esc($skill['skill']) ?></span>
                            <span class="text-sm text-neutral-700"><?= esc(lang('Jobs.skill_gap_frequency', [$skill['count']])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-3 rounded-md border border-amber-700 bg-amber-50 p-3 text-sm text-amber-900"><?= esc(lang('Jobs.skill_gap_ethics')) ?></p>
            <?php endif; ?>
        </section>

        <section class="mt-4 rounded-md border border-neutral-200 bg-white p-5 sm:p-7" aria-labelledby="salary-insight-heading">
            <h2 class="text-lg font-semibold" id="salary-insight-heading"><?= esc(lang('Jobs.salary_insight_title')) ?></h2>
            <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.salary_insight_samples', [$insights['salary']['sample_count'], $insights['salary']['minimum_samples']])) ?></p>
            <?php if ($insights['salary']['median_minimum'] === null): ?>
                <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.salary_insight_insufficient')) ?></p>
            <?php else: ?>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md border border-neutral-200 p-4">
                        <dt class="text-sm text-neutral-700"><?= esc(lang('Jobs.salary_median_minimum')) ?></dt>
                        <dd class="mt-1 text-lg font-semibold">Rp <?= esc(number_format($insights['salary']['median_minimum'], 0, ',', '.')) ?></dd>
                    </div>
                    <?php if ($insights['salary']['median_maximum'] !== null): ?>
                        <div class="rounded-md border border-neutral-200 p-4">
                            <dt class="text-sm text-neutral-700"><?= esc(lang('Jobs.salary_median_maximum')) ?></dt>
                            <dd class="mt-1 text-lg font-semibold">Rp <?= esc(number_format($insights['salary']['median_maximum'], 0, ',', '.')) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="rounded-md border border-neutral-200 p-4">
                        <dt class="text-sm text-neutral-700"><?= esc(lang('Jobs.salary_observed_range')) ?></dt>
                        <dd class="mt-1 text-sm font-semibold">
                            Rp <?= esc(number_format($insights['salary']['minimum'], 0, ',', '.')) ?>
                            <?php if ($insights['salary']['maximum'] !== null): ?>
                                - Rp <?= esc(number_format($insights['salary']['maximum'], 0, ',', '.')) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-neutral-700"><?= esc(lang('Jobs.salary_insight_caveat')) ?></p>
            <?php endif; ?>
        </section>
    </section>
<?= $this->endSection() ?>
