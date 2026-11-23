<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="ai-wizard-page mx-auto max-w-3xl rounded-md border border-line bg-paper p-5 sm:p-8" data-reveal data-ai-wizard-page data-ai-step="2">
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.ai_story_page_title')) ?></h1>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_story_page_description')) ?></p>
        <?= view('dashboard/ai-progress', ['currentStep' => 2]) ?>
        <?php if ($errors !== []): ?>
            <ul class="mt-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form
            action="<?= esc(same_origin_url((string) url_to('cv-ai-consent'))) ?>"
            method="post"
            class="mt-5 grid gap-4"
            data-ai-wizard
            data-ai-direction="forward"
            x-data="{ storyLength: <?= mb_strlen($story, 'UTF-8') ?> }"
        >
            <?= csrf_field() ?>
            <?php foreach ($profile as $field => $value): ?>
                <input type="hidden" name="<?= esc($field) ?>" value="<?= esc($value) ?>">
            <?php endforeach; ?>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-story"><?= esc(lang('Dashboard.ai_story')) ?></label>
                <textarea class="min-h-64 w-full rounded-md border border-line bg-canvas p-3 text-sm leading-6 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-story" name="story" maxlength="8000" data-ai-character-source data-character-limit="8000" x-on:input="storyLength = Array.from($event.target.value).length" required><?= esc($story) ?></textarea>
                <p class="mt-1 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_story_help')) ?></p>
                <p class="mt-1 text-xs text-muted" id="ai-story-counter">
                    <span class="ai-character-count" data-ai-character-count aria-hidden="true" x-text="storyLength"><?= mb_strlen($story, 'UTF-8') ?></span>
                    <?= esc(lang('Dashboard.ai_story_character_limit')) ?>
                </p>
                <p class="sr-only" aria-live="polite" aria-atomic="true" data-ai-character-live></p>
            </div>
            <section
                class="border border-line bg-canvas p-4"
                aria-labelledby="ai-scan-title"
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
                <h2 class="text-sm font-semibold text-ink" id="ai-scan-title"><?= esc(lang('Dashboard.ai_scan_label')) ?></h2>
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
            <div class="flex flex-wrap gap-3">
                <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Dashboard.ai_continue')) ?></button>
                <a class="inline-flex min-h-11 items-center text-sm font-medium text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-ai-start')) ?>" data-ai-direction="backward"><?= esc(lang('Dashboard.ai_back')) ?></a>
            </div>
        </form>
    </section>
    <script defer src="/assets/js/ai-wizard-loader.js"></script>
<?= $this->endSection() ?>
