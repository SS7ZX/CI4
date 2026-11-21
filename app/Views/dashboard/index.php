<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="mb-8 flex flex-col gap-6 border-b border-line pb-6 lg:flex-row lg:items-end lg:justify-between" data-reveal>
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Dashboard.workspace_kicker')) ?></p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.title')) ?></h1>
            <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.description')) ?></p>
            <?php if ($canReviewPayments): ?>
                <a class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-payment-admin')) ?>"><?= esc(lang('Payments.admin_title')) ?></a>
            <?php endif; ?>
        </div>
        <details class="w-full rounded-md border border-line bg-paper p-3 lg:max-w-xl">
            <summary class="cursor-pointer rounded-sm px-2 py-3 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"><?= esc(lang('Dashboard.create_empty')) ?></summary>
            <form action="<?= esc(same_origin_url((string) url_to('cv-create'))) ?>" method="post" class="mt-2 grid gap-2 sm:grid-cols-[minmax(12rem,1fr)_auto]">
                <?= csrf_field() ?>
                <label class="sr-only" for="new-cv-title"><?= esc(lang('Dashboard.new_cv_title')) ?></label>
                <input class="min-h-12 rounded-md border border-line bg-paper px-3 text-sm text-ink placeholder:text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="new-cv-title" name="title" type="text" maxlength="160" placeholder="<?= esc(lang('Dashboard.title_placeholder')) ?>" required>
                <button class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Dashboard.create')) ?></button>
            </form>
        </details>
    </header>

    <?php if (is_array(session('errors'))): ?>
        <ul class="mb-5 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <?php foreach (session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <section class="mb-8 rounded-md border border-line bg-paper p-5 sm:p-7" data-reveal>
        <h2 class="text-xl font-semibold text-ink"><?= esc(lang('Dashboard.ai_title')) ?></h2>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_description')) ?></p>
        <?php if ($cvAiEnabled): ?>
            <form
                action="<?= esc(same_origin_url((string) url_to('cv-ai-prepare'))) ?>"
                method="post"
                class="mt-5 grid gap-4 sm:grid-cols-2"
                x-data="{ pending: false }"
                x-on:submit="pending = true"
                x-bind:aria-busy="pending.toString()"
            >
                <?= csrf_field() ?>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="ai-full-name"><?= esc(lang('Dashboard.ai_full_name')) ?></label>
                    <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-full-name" name="full_name" type="text" maxlength="160" autocomplete="name" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="ai-email"><?= esc(lang('Dashboard.ai_email')) ?></label>
                    <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-email" name="email" type="email" inputmode="email" maxlength="254" value="<?= esc($userEmail) ?>" autocomplete="email">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="ai-phone"><?= esc(lang('Dashboard.ai_phone')) ?></label>
                    <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-phone" name="phone" type="tel" maxlength="40" autocomplete="tel">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="ai-city"><?= esc(lang('Dashboard.ai_city')) ?></label>
                    <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-city" name="city" type="text" maxlength="120" autocomplete="address-level2">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium" for="ai-story"><?= esc(lang('Dashboard.ai_story')) ?></label>
                    <textarea class="min-h-40 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-story" name="story" maxlength="8000" aria-describedby="ai-story-help" required></textarea>
                    <p class="mt-1 text-xs leading-5 text-muted" id="ai-story-help"><?= esc(lang('Dashboard.ai_story_help')) ?></p>
                </div>
                <div class="sm:col-span-2">
                    <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="submit" x-bind:disabled="pending">
                        <span x-show="!pending"><?= esc(lang('Dashboard.ai_prepare')) ?></span>
                        <span x-cloak x-show="pending"><?= esc(lang('Dashboard.ai_preparing')) ?></span>
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p class="mt-4 rounded-md border border-line bg-canvas px-4 py-3 text-sm leading-6 text-muted" role="status"><?= esc(lang('Dashboard.ai_disabled')) ?> <?= esc(lang('Dashboard.ai_setup_help')) ?></p>
        <?php endif; ?>
    </section>

    <details
        id="quick-cv"
        class="mb-8 rounded-md border border-line bg-paper p-5 sm:p-7"
        <?= $cvs === [] || old('full_name') !== null || old('headline') !== null ? 'open' : '' ?>
        data-reveal
    >
        <summary class="cursor-pointer rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700">
            <h2 class="inline text-xl font-semibold text-ink"><?= esc(lang('Dashboard.quick_title')) ?></h2>
            <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.quick_description')) ?></p>
        </summary>
        <p class="mb-5 mt-4 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.quick_optional_help')) ?></p>
        <form
            action="<?= esc(same_origin_url((string) url_to('cv-quick-create'))) ?>"
            method="post"
            class="grid gap-4 sm:grid-cols-2"
            x-data="{ pending: false }"
            x-on:submit="pending = true"
            x-bind:aria-busy="pending.toString()"
        >
            <?= csrf_field() ?>
            <div>
                <label class="mb-1 block text-sm font-medium" for="quick-full-name"><?= esc(lang('Dashboard.quick_full_name')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-full-name" name="full_name" type="text" maxlength="160" value="<?= esc(old('full_name')) ?>" autocomplete="name" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="quick-headline"><?= esc(lang('Dashboard.quick_headline')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-headline" name="headline" type="text" maxlength="160" value="<?= esc(old('headline')) ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="quick-email"><?= esc(lang('Dashboard.quick_email')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-email" name="email" type="email" inputmode="email" maxlength="254" value="<?= esc(old('email', $userEmail)) ?>" autocomplete="email">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="quick-phone"><?= esc(lang('Dashboard.quick_phone')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-phone" name="phone" type="tel" maxlength="40" value="<?= esc(old('phone')) ?>" autocomplete="tel">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="quick-city"><?= esc(lang('Dashboard.quick_city')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-city" name="city" type="text" maxlength="120" value="<?= esc(old('city')) ?>" autocomplete="address-level2">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium" for="quick-summary"><?= esc(lang('Dashboard.quick_summary')) ?></label>
                <textarea class="min-h-24 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-summary" name="summary" maxlength="3000"><?= esc(old('summary')) ?></textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="quick-experience"><?= esc(lang('Dashboard.quick_experience')) ?></label>
                <textarea class="min-h-24 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-experience" name="experience" maxlength="3000" aria-describedby="quick-experience-help"><?= esc(old('experience')) ?></textarea>
                <p class="mt-1 text-xs text-muted" id="quick-experience-help"><?= esc(lang('Dashboard.quick_experience_help')) ?></p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="quick-education"><?= esc(lang('Dashboard.quick_education')) ?></label>
                <textarea class="min-h-24 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-education" name="education" maxlength="3000" aria-describedby="quick-education-help"><?= esc(old('education')) ?></textarea>
                <p class="mt-1 text-xs text-muted" id="quick-education-help"><?= esc(lang('Dashboard.quick_education_help')) ?></p>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium" for="quick-skills"><?= esc(lang('Dashboard.quick_skills')) ?></label>
                <textarea class="min-h-20 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="quick-skills" name="skills" maxlength="3000" aria-describedby="quick-skills-help"><?= esc(old('skills')) ?></textarea>
                <p class="mt-1 text-xs text-muted" id="quick-skills-help"><?= esc(lang('Dashboard.quick_skills_help')) ?></p>
            </div>
            <div class="sm:col-span-2">
                <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="submit" x-bind:disabled="pending">
                    <span x-show="!pending"><?= esc(lang('Dashboard.quick_submit')) ?></span>
                    <span x-cloak x-show="pending"><?= esc(lang('Dashboard.quick_submitting')) ?></span>
                </button>
            </div>
        </form>
    </details>

    <?php if ($cvs === []): ?>
        <section class="grid gap-8 rounded-md border border-line bg-paper p-6 sm:p-10 md:grid-cols-[minmax(0,1fr)_15rem] md:items-center" data-reveal>
            <div class="max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Dashboard.empty_kicker')) ?></p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.empty_title')) ?></h2>
                <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.empty_description')) ?></p>
                <p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.empty_theme_description')) ?></p>
            </div>
            <?= view('themes/thumbnail', ['theme' => reset($themes), 'featured' => false, 'compact' => true]) ?>
        </section>
    <?php else: ?>
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h2 class="text-lg font-semibold text-ink"><?= esc(lang('Dashboard.your_cvs')) ?></h2>
            <p class="text-sm text-muted"><?= esc(lang('Dashboard.cv_count', [count($cvs)])) ?></p>
        </div>
        <ul class="grid items-stretch gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($cvs as $index => $cv): ?>
                <?php $lastStep = min(8, max(1, (int) $cv->last_step)); ?>
                <?php $theme = $themes[$cv->theme_key] ?? null; ?>
                <li class="motion-card flex min-w-0 flex-col overflow-hidden rounded-md border border-line bg-paper" data-reveal style="--motion-reveal-delay: <?= min(($index % 6) * 35, 175) ?>ms">
                    <?php if (is_array($theme)): ?>
                        <?= view('themes/thumbnail', ['theme' => $theme, 'featured' => false, 'compact' => true]) ?>
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
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?= $this->endSection() ?>
