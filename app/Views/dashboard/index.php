<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="mb-8 flex flex-col gap-6 border-b border-line pb-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Dashboard.workspace_kicker')) ?></p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.title')) ?></h1>
            <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.description')) ?></p>
            <?php if ($canReviewPayments): ?>
                <a class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-payment-admin')) ?>"><?= esc(lang('Payments.admin_title')) ?></a>
            <?php endif; ?>
        </div>
        <form action="<?= esc(url_to('cv-create')) ?>" method="post" class="grid w-full gap-2 sm:grid-cols-[minmax(12rem,1fr)_auto] lg:max-w-xl">
            <?= csrf_field() ?>
            <label class="sr-only" for="new-cv-title"><?= esc(lang('Dashboard.new_cv_title')) ?></label>
            <input class="min-h-12 rounded-md border border-line bg-paper px-3 text-sm text-ink placeholder:text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="new-cv-title" name="title" type="text" maxlength="160" placeholder="<?= esc(lang('Dashboard.title_placeholder')) ?>" required>
            <button class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit">
                <span aria-hidden="true" class="text-lg leading-none">+</span><?= esc(lang('Dashboard.create')) ?>
            </button>
        </form>
    </header>

    <?php if (is_array(session('errors'))): ?>
        <ul class="mb-5 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <?php foreach (session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($cvs === []): ?>
        <section class="grid gap-8 rounded-md border border-line bg-paper p-6 sm:p-10 md:grid-cols-[minmax(0,1fr)_15rem] md:items-center">
            <div class="max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Dashboard.empty_kicker')) ?></p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.empty_title')) ?></h2>
                <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.empty_description')) ?></p>
                <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.empty_theme_description')) ?></p>
            </div>
            <?= view('themes/thumbnail', ['theme' => reset($themes), 'compact' => true]) ?>
        </section>
    <?php else: ?>
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h2 class="text-lg font-semibold text-ink"><?= esc(lang('Dashboard.your_cvs')) ?></h2>
            <p class="text-sm text-muted"><?= esc(lang('Dashboard.cv_count', [count($cvs)])) ?></p>
        </div>
        <ul class="grid items-stretch gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($cvs as $cv): ?>
                <?php $lastStep = min(8, max(1, (int) $cv->last_step)); ?>
                <?php $theme = $themes[$cv->theme_key] ?? null; ?>
                <li class="flex min-w-0 flex-col overflow-hidden rounded-md border border-line bg-paper">
                    <?php if (is_array($theme)): ?>
                        <?= view('themes/thumbnail', ['theme' => $theme, 'compact' => true]) ?>
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-5">
                        <h3 class="break-words text-lg font-semibold tracking-tight text-ink"><?= esc($cv->title) ?></h3>
                        <p class="mt-1 text-xs text-muted"><?= esc(lang('Dashboard.updated')) ?>: <?= esc((string) $cv->updated_at) ?></p>
                        <?php if (is_array($theme)): ?>
                            <p class="mt-3 text-sm font-medium text-accent-900"><?= esc(lang($theme['name_key'])) ?> <span class="font-normal text-muted">, <?= esc(lang($theme['ats_friendly'] ? 'Themes.ats_badge' : 'Themes.non_ats_badge')) ?></span></p>
                        <?php endif; ?>
                        <p class="mt-2 text-xs leading-5 text-muted"><?= esc(lang('Dashboard.last_step', [$lastStep, 8])) ?></p>
                        <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 pt-5">
                            <a class="inline-flex min-h-10 items-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc(url_to('cv-step', $cv->id, $lastStep)) ?>"><?= esc(lang('Dashboard.continue')) ?></a>
                            <a class="text-sm font-medium text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-preview', $cv->id)) ?>"><?= esc(lang('Dashboard.preview')) ?></a>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                            <a class="text-xs font-medium text-muted underline underline-offset-4 hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-jobs', $cv->id)) ?>"><?= esc(lang('Jobs.find_jobs')) ?></a>
                        <form action="<?= esc(url_to('cv-duplicate', $cv->id)) ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="min-h-9 rounded-md px-2 text-xs font-medium text-muted hover:bg-canvas hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Dashboard.duplicate')) ?></button>
                        </form>
                        <form action="<?= esc(url_to('cv-delete', $cv->id)) ?>" method="post" x-on:submit.prevent="window.confirm(<?= esc(json_encode(lang('Dashboard.confirm_delete'), JSON_THROW_ON_ERROR), 'attr') ?>) && $el.submit()">
                            <?= csrf_field() ?>
                            <button class="min-h-9 rounded-md px-2 text-xs font-medium text-red-800 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="submit"><?= esc(lang('Dashboard.delete')) ?></button>
                        </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?= $this->endSection() ?>
