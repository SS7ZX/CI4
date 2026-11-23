<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="ai-wizard-page mx-auto max-w-4xl rounded-md border border-line bg-paper p-5 sm:p-8" data-reveal data-ai-wizard-page data-ai-step="3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.ai_confirm_title')) ?></h1>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_confirm_description')) ?></p>
        <?= view('dashboard/ai-progress', ['currentStep' => 3]) ?>
        <p class="mt-3 rounded-md border border-line bg-canvas px-4 py-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_story_help')) ?></p>
        <p class="mt-3 rounded-md border border-line bg-canvas px-4 py-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_consent_scope')) ?></p>
        <?php if (($targetRole ?? '') !== ''): ?>
            <p class="mt-3 text-sm leading-6 text-muted"><span class="font-medium text-ink"><?= esc(lang('Dashboard.ai_target_role')) ?>:</span> <?= esc($targetRole) ?></p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <ul class="mt-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?php foreach ($errors as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form
            action="<?= esc(same_origin_url((string) url_to('cv-ai-live'))) ?>"
            method="post"
            class="mt-5 grid gap-4"
            data-ai-wizard
            data-ai-direction="forward"
            x-data="{ pending: false, storyLength: <?= mb_strlen($story, 'UTF-8') ?> }"
            x-on:submit="pending = true"
            x-bind:aria-busy="pending.toString()"
        >
            <?= csrf_field() ?>
            <input type="hidden" name="full_name" value="<?= esc($identity['full_name']) ?>">
            <input type="hidden" name="email" value="<?= esc($identity['email']) ?>">
            <input type="hidden" name="phone" value="<?= esc($identity['phone']) ?>">
            <input type="hidden" name="city" value="<?= esc($identity['city']) ?>">
            <input type="hidden" name="target_role" value="<?= esc($targetRole ?? '') ?>">
            <div>
                <label class="mb-1 block text-sm font-medium" for="sanitized-story"><?= esc(lang('Dashboard.ai_story')) ?></label>
                <textarea class="min-h-64 w-full rounded-md border border-line bg-canvas p-3 text-sm leading-6 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="sanitized-story" name="sanitized_story" maxlength="8000" aria-describedby="sanitized-story-counter" data-ai-character-source data-character-limit="8000" x-on:input="storyLength = Array.from($event.target.value).length" required><?= esc($story) ?></textarea>
                <p class="mt-1 text-xs text-muted" id="sanitized-story-counter">
                    <span class="ai-character-count" data-ai-character-count aria-hidden="true" x-text="storyLength"><?= mb_strlen($story, 'UTF-8') ?></span>
                    <?= esc(lang('Dashboard.ai_story_character_limit')) ?>
                </p>
                <p class="sr-only" aria-live="polite" aria-atomic="true" data-ai-character-live></p>
            </div>
            <section
                class="border border-line bg-canvas p-4"
                aria-labelledby="ai-confirm-scan-title"
                data-ai-scan-panel
                data-scan-email="<?= esc(lang('Dashboard.ai_scan_email'), 'attr') ?>"
                data-scan-phone="<?= esc(lang('Dashboard.ai_scan_phone'), 'attr') ?>"
                data-scan-address="<?= esc(lang('Dashboard.ai_scan_address'), 'attr') ?>"
                data-scan-identity="<?= esc(lang('Dashboard.ai_scan_identity'), 'attr') ?>"
                data-mask-email="<?= esc(lang('Dashboard.ai_scan_mask_email'), 'attr') ?>"
                data-mask-phone="<?= esc(lang('Dashboard.ai_scan_mask_phone'), 'attr') ?>"
                data-mask-address="<?= esc(lang('Dashboard.ai_scan_mask_address'), 'attr') ?>"
                data-mask-identity="<?= esc(lang('Dashboard.ai_scan_mask_identity'), 'attr') ?>"
                data-scan-none="<?= esc(lang('Dashboard.ai_scan_none'), 'attr') ?>"
                data-scan-error="<?= esc(lang('Dashboard.ai_wizard_load_error'), 'attr') ?>"
                data-scan-count="<?= esc(lang('Dashboard.ai_scan_count'), 'attr') ?>"
                data-counter-limit="<?= esc(lang('Dashboard.ai_scan_counter_limit'), 'attr') ?>"
                data-counter-warning="<?= esc(lang('Dashboard.ai_scan_counter_warning'), 'attr') ?>"
                data-counter-normal="<?= esc(lang('Dashboard.ai_scan_counter_normal'), 'attr') ?>"
            >
                <h2 class="text-sm font-semibold text-ink" id="ai-confirm-scan-title"><?= esc(lang('Dashboard.ai_scan_label')) ?></h2>
                <p class="mt-1 text-xs leading-5 text-muted"><?= esc(lang('Dashboard.ai_scan_description')) ?></p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <p class="text-sm text-muted" role="status" aria-live="polite" aria-atomic="true" data-ai-scan-live><?= esc(lang('Dashboard.ai_scan_none')) ?></p>
                    <span class="ai-pii-count motion-count-up border border-line px-2 py-1 text-xs" aria-hidden="true" data-ai-scan-count>0</span>
                </div>
                <ul class="mt-2 flex flex-wrap gap-2" data-ai-scan-categories></ul>
                <fieldset class="mt-3 flex flex-wrap items-center gap-2" data-ai-mask-controls>
                    <legend class="mr-1 text-xs font-medium text-muted"><?= esc(lang('Dashboard.ai_scan_preview_label')) ?></legend>
                    <button class="ai-mask-control rounded-sm border border-line px-2 py-1 text-xs" type="button" data-ai-mask-mode="masked" aria-pressed="true"><?= esc(lang('Dashboard.ai_scan_mask')) ?></button>
                    <button class="ai-mask-control rounded-sm border border-line px-2 py-1 text-xs" type="button" data-ai-mask-mode="original" aria-pressed="false"><?= esc(lang('Dashboard.ai_scan_original')) ?></button>
                </fieldset>
                <p class="ai-pii-scan mt-3 max-h-40 overflow-auto whitespace-pre-wrap break-words border-t border-line pt-3 text-xs leading-5 text-muted" data-ai-scan-preview></p>
            </section>
            <label class="flex items-start gap-3 rounded-md border border-line bg-canvas p-4 text-sm leading-6 text-ink">
                <input class="mt-1 size-4 accent-accent-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="checkbox" name="ai_consent" value="1" required>
                <span><?= esc(lang('Dashboard.ai_consent_label')) ?></span>
            </label>
            <div class="flex flex-wrap items-center gap-3">
                <button class="motion-button inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="submit" x-bind:disabled="pending" x-bind:aria-busy="pending.toString()">
                    <span class="motion-button-label" x-show="!pending"><?= esc(lang('Dashboard.ai_generate')) ?></span>
                    <span class="sr-only" x-cloak x-show="pending"><?= esc(lang('Dashboard.ai_generating')) ?></span>
                </button>
                <a class="inline-flex min-h-11 items-center text-sm font-medium text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.ai_back')) ?></a>
            </div>
        </form>
    </section>
    <script defer src="/assets/js/ai-wizard-loader.js"></script>
<?= $this->endSection() ?>
