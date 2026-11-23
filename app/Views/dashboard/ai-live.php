<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section
        class="ai-wizard-page ai-stream-root mx-auto max-w-3xl rounded-md border border-line bg-paper p-5 sm:p-8"
        data-reveal
        data-ai-wizard-page
        data-ai-step="4"
        data-cv-ai-stream-root
        data-review-url="<?= esc(same_origin_url((string) url_to('cv-ai-review'))) ?>"
        data-csrf-token-name="<?= esc(config(\Config\Security::class)->tokenName) ?>"
        data-csrf-header-name="<?= esc(config(\Config\Security::class)->headerName) ?>"
        data-generating="<?= esc(lang('Dashboard.ai_live_generating')) ?>"
        data-failed="<?= esc(lang('Dashboard.ai_live_failed')) ?>"
        data-csrf-error="<?= esc(lang('Dashboard.ai_live_csrf')) ?>"
        data-disconnected="<?= esc(lang('Dashboard.ai_live_disconnected')) ?>"
        data-cancelled="<?= esc(lang('Dashboard.ai_live_cancelled')) ?>"
        data-timeout="<?= esc(lang('Dashboard.ai_live_timeout')) ?>"
    >
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.ai_live_title')) ?></h1>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_live_description')) ?></p>
        <?= view('dashboard/ai-progress', ['currentStep' => 4]) ?>
        <form action="<?= esc(same_origin_url((string) url_to('cv-ai-generate'))) ?>" method="post" data-cv-ai-stream-form data-ai-direction="forward" class="mt-5 grid gap-4">
            <?= csrf_field() ?>
            <?php foreach ($input as $field => $value): ?>
                <input type="hidden" name="<?= esc($field) ?>" value="<?= esc($value) ?>">
            <?php endforeach; ?>
            <div class="ai-stream-skeleton" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
            <pre class="ai-stream-output min-h-40 max-h-80 overflow-auto whitespace-pre-wrap rounded-md border border-line bg-canvas p-4 text-sm leading-6 text-ink" data-cv-ai-partial aria-live="polite" aria-label="<?= esc(lang('Dashboard.ai_live_output_label')) ?>"></pre>
            <p class="text-sm leading-6 text-muted" role="status" aria-live="polite" data-cv-ai-status><?= esc(lang('Dashboard.ai_live_waiting')) ?></p>
            <div class="flex flex-wrap gap-3">
                <button class="motion-button inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit" data-cv-ai-start aria-busy="false"><span class="motion-button-label"><?= esc(lang('Dashboard.ai_live_start')) ?></span></button>
                <button class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-paper px-5 text-sm font-semibold text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="button" data-cv-ai-cancel disabled><?= esc(lang('Dashboard.ai_live_cancel')) ?></button>
            </div>
        </form>
        <noscript><p class="mt-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_live_no_script')) ?></p></noscript>
    </section>
    <script defer src="/assets/js/cv-ai-stream.js"></script>
<?= $this->endSection() ?>
