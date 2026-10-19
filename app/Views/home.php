<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$atsThemeCount = count(array_filter($themes, static fn (array $theme): bool => $theme['ats_friendly']));
$featuredTheme = array_values(array_filter($themes, static fn (array $theme): bool => $theme['key'] === 'ats-classic'))[0];
?>
<section class="home-hero overflow-hidden rounded-md border border-line">
    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.82fr)]">
        <div class="flex flex-col justify-center px-6 py-10 sm:px-10 sm:py-14 lg:px-14 lg:py-16">
            <p class="inline-flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.16em] text-accent-900">
                <span class="h-px w-8 bg-accent-700" aria-hidden="true"></span>
                <?= esc(lang('App.home_kicker')) ?>
            </p>
            <h1 class="home-hero__title mt-6 max-w-xl text-4xl font-medium leading-[1.08] tracking-tight text-ink sm:text-5xl">
                <?= esc(lang('App.home_title')) ?>
            </h1>
            <p class="mt-5 max-w-lg text-base leading-7 text-muted">
                <?= esc(lang('App.home_description')) ?>
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-4">
                <?php if (auth()->loggedIn()): ?>
                    <a class="inline-flex min-h-12 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc(url_to('dashboard')) ?>">
                        <?= esc(lang('App.home_primary_action')) ?>
                    </a>
                <?php else: ?>
                    <a class="inline-flex min-h-12 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc(url_to('register')) ?>">
                        <?= esc(lang('App.home_primary_action')) ?>
                    </a>
                <?php endif; ?>
                <a class="inline-flex min-h-12 items-center justify-center rounded-md border border-ink px-5 text-sm font-semibold text-ink hover:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="#themes">
                    <?= esc(lang('App.home_secondary_action')) ?>
                </a>
            </div>
            <p class="mt-5 text-xs leading-5 text-muted"><?= esc(lang('App.home_account_note')) ?></p>
        </div>
        <div class="home-hero__art relative flex min-h-[25rem] flex-col items-center justify-center overflow-hidden px-6 py-10 sm:min-h-[30rem]">
            <div class="home-hero__caption absolute left-5 top-5 flex items-center gap-3 text-xs font-medium uppercase tracking-[0.12em] text-muted sm:left-8 sm:top-8">
                <span class="home-hero__caption-mark" aria-hidden="true"></span>
                <?= esc(lang('App.home_preview_label')) ?>
            </div>
            <?= view('themes/thumbnail', ['theme' => $featuredTheme, 'featured' => true]) ?>
            <p class="home-hero__preview-note absolute bottom-5 left-5 right-5 text-center text-xs leading-5 text-muted sm:bottom-8"><?= esc(lang('App.home_preview_note')) ?></p>
        </div>
    </div>
</section>

<section class="home-process mt-12 border-y border-line sm:mt-16" aria-label="<?= esc(lang('App.home_process_title')) ?>">
    <ol class="grid sm:grid-cols-3">
        <?php foreach (lang('App.home_process') as $index => $item): ?>
            <li class="flex gap-4 border-b border-line py-5 last:border-0 sm:border-b-0 sm:border-r sm:px-5 sm:first:pl-0 sm:last:border-0">
                <span class="home-process__number text-xs font-semibold text-accent-900"><?= sprintf('%02d', $index + 1) ?></span>
                <span class="text-sm leading-6 text-ink"><?= esc($item) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<section class="mt-16 sm:mt-20" id="themes">
    <div class="flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('App.home_themes_kicker')) ?></p>
            <h2 class="home-section-title mt-2 text-3xl font-medium tracking-tight text-ink sm:text-4xl"><?= esc(lang('App.home_themes_title')) ?></h2>
            <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('App.home_themes_description')) ?></p>
        </div>
        <p class="text-xs font-medium uppercase tracking-[0.12em] text-muted"><?= esc(lang('App.home_theme_count', [count($themes), $atsThemeCount])) ?></p>
    </div>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($themes as $theme): ?>
            <li class="theme-card flex flex-col overflow-hidden border border-line bg-paper">
                <?= view('themes/thumbnail', ['theme' => $theme, 'compact' => true]) ?>
                <div class="flex flex-1 flex-col px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-lg font-semibold tracking-tight text-ink"><?= esc(lang($theme['name_key'])) ?></h3>
                        <span class="text-[0.68rem] font-semibold uppercase tracking-[0.08em] <?= $theme['ats_friendly'] ? 'text-accent-900' : 'text-muted' ?>">
                            <?= esc(lang($theme['ats_friendly'] ? 'Themes.ats_badge' : 'Themes.non_ats_badge')) ?>
                        </span>
                    </div>
                    <p class="mt-2 text-sm leading-5 text-muted"><?= esc(lang($theme['description_key'])) ?></p>
                    <?php if (! $theme['ats_friendly']): ?>
                        <p class="mt-3 text-xs leading-5 text-muted"><?= esc(lang('Themes.ats_visual_warning')) ?></p>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="mt-4 max-w-3xl text-xs leading-5 text-muted"><?= esc(lang('Themes.ats_scope_note')) ?></p>
</section>

<section class="home-final mt-16 flex flex-col gap-5 border-t border-line py-8 sm:mt-20 sm:flex-row sm:items-center sm:justify-between">
    <div class="max-w-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('App.home_bottom_kicker')) ?></p>
        <h2 class="home-section-title mt-2 text-2xl font-medium tracking-tight text-ink sm:text-3xl"><?= esc(lang('App.home_bottom_title')) ?></h2>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('App.home_bottom_description')) ?></p>
    </div>
    <?php if (auth()->loggedIn()): ?>
        <a class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc(url_to('dashboard')) ?>">
            <?= esc(lang('App.home_primary_action')) ?>
        </a>
    <?php else: ?>
        <a class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc(url_to('register')) ?>">
            <?= esc(lang('App.home_primary_action')) ?>
        </a>
    <?php endif; ?>
</section>
<?= $this->endSection() ?>
