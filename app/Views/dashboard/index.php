<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <?php
    $themeCount = count($themes);
    $atsThemeCount = count(array_filter($themes, static fn (array $theme): bool => $theme['ats_friendly']));
    $featuredCv = $cvs[0] ?? null;
    $featuredTheme = $featuredCv === null ? null : ($themes[$featuredCv->theme_key] ?? null);
    $featuredStep = $featuredCv === null ? 1 : min(8, max(1, (int) $featuredCv->last_step));
    $featuredProgress = (int) round($featuredStep / 8 * 100);
    $aiReady = $cvAiStatus['available'] && $cvAiStatus['model_installed'];
    $aiStatusLabel = match (true) {
        $aiReady => lang('Dashboard.ai_status_ready'),
        $cvAiStatus['reason'] === 'model_missing' => lang('Dashboard.ai_status_model_missing'),
        $cvAiStatus['reason'] === 'unavailable' => lang('Dashboard.ai_status_unavailable'),
        default => lang('Dashboard.ai_status_disabled'),
    };
    ?>

    <header class="relative isolate mb-8 overflow-hidden rounded-md border border-line bg-paper p-5 sm:p-8 lg:p-10" data-reveal>
        <span class="absolute inset-y-0 left-0 w-1 bg-accent-700" aria-hidden="true"></span>
        <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.55fr)] lg:items-end">
            <div class="max-w-3xl">
                <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-accent-900">
                    <span class="size-2 rounded-full bg-accent-700" aria-hidden="true"></span>
                    <?= esc(lang('Dashboard.workspace_kicker')) ?>
                </p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-ink sm:text-4xl"><?= esc(lang('Dashboard.title')) ?></h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-muted"><?= esc(lang('Dashboard.description')) ?></p>
            </div>
            <div class="flex flex-col gap-2">
                <a class="motion-action group inline-flex min-h-12 items-center justify-between gap-4 rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="#quick-cv" x-on:click="$refs.quickCv.open = true">
                    <span><?= esc(lang('Dashboard.quick_title')) ?></span>
                    <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6"></path>
                    </svg>
                </a>
                <details class="group rounded-md border border-line bg-canvas p-3">
                    <summary class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3 rounded-sm px-2 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 [&::-webkit-details-marker]:hidden">
                        <span><?= esc(lang('Dashboard.create_empty')) ?></span>
                        <svg class="size-4 shrink-0 text-muted transition-transform duration-fast group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </summary>
                    <form action="<?= esc(same_origin_url((string) url_to('cv-create'))) ?>" method="post" class="mt-3 grid gap-2">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="new-cv-title"><?= esc(lang('Dashboard.new_cv_title')) ?></label>
                        <input class="min-h-11 w-full min-w-0 rounded-md border border-line bg-paper px-3 text-sm text-ink placeholder:text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="new-cv-title" name="title" type="text" maxlength="160" placeholder="<?= esc(lang('Dashboard.title_placeholder')) ?>" required>
                        <button class="motion-action group inline-flex min-h-11 w-full items-center justify-between gap-3 rounded-md border border-line bg-paper px-4 text-sm font-semibold text-ink hover:border-accent-700 hover:bg-accent-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit">
                            <span><?= esc(lang('Dashboard.create')) ?></span>
                            <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6"></path>
                            </svg>
                        </button>
                    </form>
                </details>
                <?php if ($canReviewPayments): ?>
                    <a class="inline-flex min-h-10 items-center justify-center rounded-md px-3 text-sm font-semibold text-muted hover:bg-canvas hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-payment-admin')) ?>"><?= esc(lang('Payments.admin_title')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if (is_array(session('errors'))): ?>
        <ul class="mb-5 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <?php foreach (session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <section class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="<?= esc(lang('Dashboard.workspace_summary')) ?>" data-reveal>
        <article class="motion-card rounded-md border border-line border-t border-t-accent-700 bg-paper p-5">
            <div class="flex items-start justify-between gap-4">
                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-muted"><?= esc(lang('Dashboard.stat_documents')) ?></span>
                <span class="grid size-10 shrink-0 place-items-center rounded-md border border-line bg-canvas text-accent-900" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z"></path>
                        <path d="M14 4v4h4M8 13h8M8 16.5h8"></path>
                    </svg>
                </span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight text-ink"><?= esc((string) count($cvs)) ?></p>
        </article>
        <article class="motion-card rounded-md border border-line bg-paper p-5">
            <div class="flex items-start justify-between gap-4">
                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-muted"><?= esc(lang('Dashboard.stat_themes')) ?></span>
                <span class="grid size-10 shrink-0 place-items-center rounded-md border border-line bg-canvas text-accent-900" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M12 3.5a8.5 8.5 0 1 0 0 17h1.2a2.1 2.1 0 0 0 1.6-3.5 1.6 1.6 0 0 1 1.2-2.7h1.1A3.4 3.4 0 0 0 20.5 11 7.5 7.5 0 0 0 12 3.5Z"></path>
                        <path d="M7.5 10h.01M10 7.5h.01M14 7.5h.01M16.5 10h.01"></path>
                    </svg>
                </span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight text-ink"><?= esc((string) $themeCount) ?></p>
        </article>
        <article class="motion-card rounded-md border border-line bg-paper p-5">
            <div class="flex items-start justify-between gap-4">
                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-muted"><?= esc(lang('Dashboard.stat_ats_themes')) ?></span>
                <span class="grid size-10 shrink-0 place-items-center rounded-md border border-line bg-canvas text-accent-900" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M12 3.5 19 6v5.3c0 4.3-2.8 7.3-7 9.2-4.2-1.9-7-4.9-7-9.2V6l7-2.5Z"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                </span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight text-ink"><?= esc((string) $atsThemeCount) ?></p>
        </article>
        <article class="motion-card rounded-md border border-line bg-paper p-5">
            <div class="flex items-start justify-between gap-4">
                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-muted"><?= esc(lang('Dashboard.stat_ai')) ?></span>
                <span class="grid size-10 shrink-0 place-items-center rounded-md border border-line bg-canvas text-accent-900" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <rect x="4" y="4" width="16" height="16" rx="3"></rect>
                        <path d="M9 10h.01M15 10h.01M8.5 15h7M2 9h2M2 15h2M20 9h2M20 15h2M9 2v2M15 2v2M9 20v2M15 20v2"></path>
                    </svg>
                </span>
            </div>
            <p class="mt-5 text-base font-semibold leading-6 text-ink"><?= esc($aiStatusLabel) ?></p>
        </article>
    </section>

    <?php if ($featuredCv !== null): ?>
        <section class="mb-8" aria-labelledby="latest-cv-title" data-reveal>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-accent-900"><?= esc(lang('Dashboard.latest_kicker')) ?></p>
                    <h2 class="text-xl font-semibold tracking-tight text-ink" id="latest-cv-title"><?= esc(lang('Dashboard.latest_title')) ?></h2>
                </div>
                <span class="inline-flex min-h-8 items-center rounded-md border border-line bg-paper px-3 text-xs font-semibold text-muted"><?= esc(lang('Dashboard.last_step', [$featuredStep, 8])) ?></span>
            </div>
            <div class="motion-card grid overflow-hidden rounded-md border border-line bg-paper lg:grid-cols-[minmax(0,1.05fr)_minmax(18rem,0.95fr)]">
                <div class="flex min-h-72 items-center justify-center border-b border-line bg-canvas p-5 sm:p-8 lg:border-b-0 lg:border-r">
                    <?php if (is_array($featuredTheme)): ?>
                        <?= view('themes/thumbnail', ['theme' => $featuredTheme, 'featured' => true, 'compact' => false, 'motion' => true]) ?>
                    <?php else: ?>
                        <div class="flex min-h-56 w-full max-w-sm items-center justify-center rounded-md border border-dashed border-line bg-paper px-6 text-center text-sm leading-6 text-muted">
                            <?= esc(lang('Dashboard.latest_theme_missing')) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col justify-between p-5 sm:p-7 lg:p-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted"><?= esc(lang('Dashboard.latest_document_label')) ?></p>
                        <h3 class="mt-2 wrap-break-word text-2xl font-semibold tracking-tight text-ink"><?= esc($featuredCv->title) ?></h3>
                        <?php if (is_array($featuredTheme)): ?>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-accent-900"><?= esc(lang($featuredTheme['name_key'])) ?></span>
                                <span class="inline-flex min-h-7 items-center rounded-md border border-line bg-canvas px-2.5 text-xs font-semibold text-ink">
                                    <?= esc(lang($featuredTheme['ats_friendly'] ? 'Themes.ats_badge' : 'Themes.non_ats_badge')) ?>
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang($featuredTheme['ats_friendly'] ? 'Themes.ats_scope_note' : 'Themes.ats_visual_warning')) ?></p>
                        <?php else: ?>
                            <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.latest_theme_missing')) ?></p>
                        <?php endif; ?>
                        <div class="mt-6">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-muted"><?= esc(lang('Dashboard.latest_progress')) ?></span>
                                <span class="text-xs font-semibold tabular-nums text-ink"><?= esc(lang('Dashboard.last_step', [$featuredStep, 8])) ?></span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-canvas" role="progressbar" aria-label="<?= esc(lang('Dashboard.latest_progress')) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= esc((string) $featuredProgress) ?>">
                                <span class="motion-progress-fill block h-full rounded-full bg-accent-700" style="--progress-value: <?= esc((string) ($featuredProgress / 100)) ?>"></span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-line pt-4">
                        <a class="motion-action group inline-flex min-h-11 items-center justify-between gap-3 rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc(url_to('cv-step', $featuredCv->id, $featuredStep)) ?>">
                            <span><?= esc(lang('Dashboard.continue')) ?></span>
                            <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6"></path>
                            </svg>
                        </a>
                        <a class="motion-action group inline-flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold text-accent-900 hover:bg-accent-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-preview', $featuredCv->id)) ?>">
                            <span><?= esc(lang('Dashboard.preview')) ?></span>
                            <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path d="M2.5 12s3.2-5.5 9.5-5.5 9.5 5.5 9.5 5.5-3.2 5.5-9.5 5.5S2.5 12 2.5 12Z"></path>
                                <circle cx="12" cy="12" r="2.5"></circle>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="motion-card mb-8 grid gap-5 rounded-md border border-line bg-accent-50 p-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:p-7" aria-labelledby="ai-title" data-reveal>
        <span class="grid size-12 place-items-center rounded-md border border-line bg-paper text-accent-900" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M12 3.5v3M12 17.5v3M3.5 12h3M17.5 12h3M5.99 5.99l2.12 2.12M15.89 15.89l2.12 2.12M18.01 5.99l-2.12 2.12M8.11 15.89l-2.12 2.12"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        </span>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-semibold tracking-tight text-ink" id="ai-title"><?= esc(lang('Dashboard.ai_title')) ?></h2>
                <span class="inline-flex min-h-7 items-center rounded-md border border-line bg-paper px-2.5 text-xs font-semibold text-ink"><?= esc($aiStatusLabel) ?></span>
            </div>
            <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_description')) ?></p>
            <?php if (! $aiReady): ?>
                <?php if ($cvAiStatus['reason'] === 'model_missing'): ?>
                    <p class="mt-3 text-sm leading-6 text-muted" role="status"><?= esc(lang('Dashboard.ai_error_model_missing')) ?></p>
                <?php elseif ($cvAiStatus['reason'] === 'unavailable'): ?>
                    <p class="mt-3 text-sm leading-6 text-muted" role="status"><?= esc(lang('Dashboard.ai_error_unavailable')) ?></p>
                <?php else: ?>
                    <p class="mt-3 text-sm leading-6 text-muted" role="status"><?= esc(lang('Dashboard.ai_disabled')) ?> <?= esc(lang('Dashboard.ai_setup_help')) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($aiReady): ?>
            <a class="motion-action group inline-flex min-h-11 items-center justify-between gap-3 rounded-md border border-accent-700 bg-paper px-5 text-sm font-semibold text-accent-900 hover:bg-accent-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-ai-start')) ?>">
                <span><?= esc(lang('Dashboard.ai_start_action')) ?></span>
                <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6"></path>
                </svg>
            </a>
        <?php endif; ?>
    </section>

    <details
        id="quick-cv"
        x-ref="quickCv"
        class="group mb-8 rounded-md border border-line bg-paper"
        <?= $cvs === [] || old('full_name') !== null || old('headline') !== null ? 'open' : '' ?>
        data-reveal
    >
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-md p-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 sm:p-7 [&::-webkit-details-marker]:hidden">
            <span class="flex min-w-0 items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-md border border-line bg-canvas text-accent-900" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M12 5v14M5 12h14"></path>
                    </svg>
                </span>
                <span class="min-w-0">
                    <span class="block text-xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.quick_title')) ?></span>
                    <span class="mt-2 block text-sm leading-6 text-muted"><?= esc(lang('Dashboard.quick_description')) ?></span>
                </span>
            </span>
            <svg class="size-5 shrink-0 text-muted transition-transform duration-fast group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="m6 9 6 6 6-6"></path>
            </svg>
        </summary>
        <div class="border-t border-line bg-canvas p-4 sm:p-7">
            <p class="mb-5 max-w-3xl rounded-md border border-line bg-paper px-4 py-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.quick_optional_help')) ?></p>
            <form
                action="<?= esc(same_origin_url((string) url_to('cv-quick-create'))) ?>"
                method="post"
                class="grid gap-4"
                x-data="{ pending: false }"
                x-on:submit="pending = true"
                x-bind:aria-busy="pending.toString()"
            >
                <?= csrf_field() ?>
                <fieldset class="grid gap-4 rounded-md border border-line bg-paper p-4 sm:grid-cols-2 sm:p-5">
                    <legend class="px-2 text-sm font-semibold text-ink"><?= esc(lang('Dashboard.quick_section_profile')) ?></legend>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" for="quick-full-name"><?= esc(lang('Dashboard.quick_full_name')) ?></label>
                        <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-full-name" name="full_name" type="text" maxlength="160" value="<?= esc(old('full_name')) ?>" autocomplete="name" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" for="quick-headline"><?= esc(lang('Dashboard.quick_headline')) ?></label>
                        <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-headline" name="headline" type="text" maxlength="160" value="<?= esc(old('headline')) ?>" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" for="quick-email"><?= esc(lang('Dashboard.quick_email')) ?></label>
                        <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-email" name="email" type="email" inputmode="email" maxlength="254" value="<?= esc(old('email', $userEmail)) ?>" autocomplete="email">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" for="quick-phone"><?= esc(lang('Dashboard.quick_phone')) ?></label>
                        <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-phone" name="phone" type="tel" maxlength="40" value="<?= esc(old('phone')) ?>" autocomplete="tel">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium" for="quick-city"><?= esc(lang('Dashboard.quick_city')) ?></label>
                        <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 sm:max-w-md" id="quick-city" name="city" type="text" maxlength="120" value="<?= esc(old('city')) ?>" autocomplete="address-level2">
                    </div>
                </fieldset>
                <fieldset class="grid gap-4 rounded-md border border-line bg-paper p-4 sm:grid-cols-2 sm:p-5">
                    <legend class="px-2 text-sm font-semibold text-ink"><?= esc(lang('Dashboard.quick_section_experience')) ?></legend>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium" for="quick-summary"><?= esc(lang('Dashboard.quick_summary')) ?></label>
                        <textarea class="min-h-28 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-summary" name="summary" maxlength="3000"><?= esc(old('summary')) ?></textarea>
                        <p class="mt-1 text-sm leading-6 text-muted"><?= esc(lang('CvBuilder.summary_guidance')) ?></p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" for="quick-experience"><?= esc(lang('Dashboard.quick_experience')) ?></label>
                        <textarea class="min-h-28 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-experience" name="experience" maxlength="3000" aria-describedby="quick-experience-help"><?= esc(old('experience')) ?></textarea>
                        <p class="mt-1 text-xs leading-5 text-muted" id="quick-experience-help"><?= esc(lang('Dashboard.quick_experience_help')) ?></p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" for="quick-education"><?= esc(lang('Dashboard.quick_education')) ?></label>
                        <textarea class="min-h-28 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-education" name="education" maxlength="3000" aria-describedby="quick-education-help"><?= esc(old('education')) ?></textarea>
                        <p class="mt-1 text-xs leading-5 text-muted" id="quick-education-help"><?= esc(lang('Dashboard.quick_education_help')) ?></p>
                    </div>
                </fieldset>
                <fieldset class="grid gap-4 rounded-md border border-line bg-paper p-4 sm:p-5">
                    <legend class="px-2 text-sm font-semibold text-ink"><?= esc(lang('Dashboard.quick_section_skills')) ?></legend>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" for="quick-skills"><?= esc(lang('Dashboard.quick_skills')) ?></label>
                        <textarea class="min-h-24 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus:border-accent-700 focus:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-skills" name="skills" maxlength="3000" aria-describedby="quick-skills-help"><?= esc(old('skills')) ?></textarea>
                        <p class="mt-1 text-xs leading-5 text-muted" id="quick-skills-help"><?= esc(lang('Dashboard.quick_skills_help')) ?></p>
                    </div>
                </fieldset>
                <div class="flex flex-wrap items-center justify-end gap-4 border-t border-line pt-4">
                    <button class="motion-button motion-action group inline-flex min-h-11 items-center justify-between gap-3 rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="submit" x-bind:disabled="pending" x-bind:aria-busy="pending.toString()">
                        <span class="motion-button-label" x-show="!pending"><?= esc(lang('Dashboard.quick_submit')) ?></span>
                        <span class="sr-only" x-cloak x-show="pending"><?= esc(lang('Dashboard.quick_submitting')) ?></span>
                        <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </details>

    <?php if ($cvs === []): ?>
        <section class="motion-card grid gap-6 rounded-md border border-line bg-paper p-5 sm:p-8 md:grid-cols-[minmax(0,1fr)_15rem] md:items-center lg:p-10" data-reveal>
            <div class="max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Dashboard.empty_kicker')) ?></p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.empty_title')) ?></h2>
                <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.empty_description')) ?></p>
                <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.empty_theme_description')) ?></p>
                <a class="motion-action group mt-5 inline-flex min-h-11 items-center gap-3 rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="#quick-cv" x-on:click="$refs.quickCv.open = true">
                    <span><?= esc(lang('Dashboard.quick_title')) ?></span>
                    <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6"></path>
                    </svg>
                </a>
            </div>
            <div class="rounded-md border border-line bg-canvas p-3 sm:p-5">
                <?= view('themes/thumbnail', ['theme' => reset($themes), 'featured' => false, 'compact' => true, 'motion' => true]) ?>
            </div>
        </section>
    <?php else: ?>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-accent-900"><?= esc(lang('Dashboard.archive_kicker')) ?></p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.your_cvs')) ?></h2>
            </div>
            <span class="inline-flex min-h-8 items-center rounded-md border border-line bg-paper px-3 text-xs font-semibold text-muted"><?= esc(lang('Dashboard.cv_count', [count($cvs)])) ?></span>
        </div>
        <ul class="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($cvs as $index => $cv): ?>
                <?php $lastStep = min(8, max(1, (int) $cv->last_step)); ?>
                <?php $progress = (int) round($lastStep / 8 * 100); ?>
                <?php $theme = $themes[$cv->theme_key] ?? null; ?>
                <li class="motion-card dashboard-cv-card flex min-w-0 flex-col rounded-md border border-line bg-paper" data-reveal style="--i: <?= $index % 6 ?>">
                    <?php if (is_array($theme)): ?>
                        <?= view('themes/thumbnail', ['theme' => $theme, 'featured' => false, 'compact' => true, 'motion' => true]) ?>
                    <?php else: ?>
                        <div class="flex min-h-28 items-center justify-center border-b border-line bg-canvas px-4 text-center text-xs leading-5 text-muted"><?= esc(lang('Dashboard.latest_theme_missing')) ?></div>
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="min-w-0 wrap-break-word text-lg font-semibold tracking-tight text-ink"><?= esc($cv->title) ?></h3>
                            <?php if (is_array($theme)): ?>
                                <span class="inline-flex min-h-7 shrink-0 items-center rounded-md border border-line bg-canvas px-2 text-xs font-semibold text-muted"><?= esc(lang($theme['ats_friendly'] ? 'Themes.ats_badge' : 'Themes.non_ats_badge')) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-muted"><?= esc(lang('Dashboard.updated')) ?>: <?= esc((string) $cv->updated_at) ?></p>
                        <?php if (is_array($theme)): ?>
                            <p class="mt-3 text-sm font-semibold text-accent-900"><?= esc(lang($theme['name_key'])) ?></p>
                        <?php endif; ?>
                        <div class="mt-4">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <span class="text-xs text-muted"><?= esc(lang('Dashboard.last_step', [$lastStep, 8])) ?></span>
                                <span class="text-xs font-semibold tabular-nums text-ink"><?= esc((string) $progress) ?>%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-canvas" role="progressbar" aria-label="<?= esc(lang('Dashboard.latest_progress')) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= esc((string) $progress) ?>">
                                <span class="motion-progress-fill block h-full rounded-full bg-accent-700" style="--progress-value: <?= esc((string) ($progress / 100)) ?>"></span>
                            </div>
                        </div>
                        <div class="mt-auto flex flex-wrap items-center gap-2 pt-5">
                            <a class="motion-action group inline-flex min-h-10 items-center gap-2 rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" href="<?= esc(url_to('cv-step', $cv->id, $lastStep)) ?>">
                                <span><?= esc(lang('Dashboard.continue')) ?></span>
                                <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6"></path>
                                </svg>
                            </a>
                            <a class="motion-action group inline-flex min-h-10 items-center gap-2 rounded-md px-3 text-sm font-medium text-accent-900 hover:bg-accent-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-preview', $cv->id)) ?>">
                                <span><?= esc(lang('Dashboard.preview')) ?></span>
                                <svg class="motion-action-icon size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path d="M2.5 12s3.2-5.5 9.5-5.5 9.5 5.5 9.5 5.5-3.2 5.5-9.5 5.5S2.5 12 2.5 12Z"></path>
                                    <circle cx="12" cy="12" r="2.5"></circle>
                                </svg>
                            </a>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                            <a class="text-xs font-medium text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-jobs', $cv->id)) ?>"><?= esc(lang('Jobs.find_jobs')) ?></a>
                            <div class="flex items-center gap-1">
                                <form action="<?= esc(same_origin_url((string) url_to('cv-duplicate', $cv->id))) ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button class="min-h-9 rounded-md px-2 text-xs font-medium text-muted hover:bg-canvas hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Dashboard.duplicate')) ?></button>
                                </form>
                                <form action="<?= esc(same_origin_url((string) url_to('cv-delete', $cv->id))) ?>" method="post" x-on:submit.prevent="window.confirm(<?= esc(json_encode(lang('Dashboard.confirm_delete'), JSON_THROW_ON_ERROR), 'attr') ?>) && $el.submit()">
                                    <?= csrf_field() ?>
                                    <button class="min-h-9 rounded-md px-2 text-xs font-medium text-red-800 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="submit"><?= esc(lang('Dashboard.delete')) ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?= $this->endSection() ?>
