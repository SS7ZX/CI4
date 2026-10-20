<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <script type="application/json" id="cv-builder-state"><?= json_encode($state, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
    <script defer src="<?= esc(base_url('assets/js/cv-builder.js')) ?>"></script>

    <div
        x-data="cvBuilder({
            step: <?= (int) $step ?>,
            saveUrl: <?= esc(json_encode($saveUrl, JSON_THROW_ON_ERROR), 'attr') ?>,
            editUrl: <?= esc(json_encode(url_to('cv-step', $cvId, $step), JSON_THROW_ON_ERROR), 'attr') ?>,
            photoUrl: <?= esc(json_encode($photoUrl, JSON_THROW_ON_ERROR), 'attr') ?>,
            photoView: <?= esc(json_encode($photoView, JSON_THROW_ON_ERROR), 'attr') ?>,
            themePreviewUrl: <?= esc(json_encode($themePreviewUrl, JSON_THROW_ON_ERROR), 'attr') ?>,
            previewUrl: <?= esc(json_encode($previewUrl, JSON_THROW_ON_ERROR), 'attr') ?>,
            pageCountUrl: <?= esc(json_encode($pageCountUrl, JSON_THROW_ON_ERROR), 'attr') ?>,
            downloadUrl: <?= esc(json_encode($downloadUrl, JSON_THROW_ON_ERROR), 'attr') ?>,
            paymentPaid: <?= json_encode($paymentPaid, JSON_THROW_ON_ERROR) ?>,
            themes: <?= esc(json_encode($themes, JSON_THROW_ON_ERROR), 'attr') ?>,
            tokenName: <?= esc(json_encode($tokenName, JSON_THROW_ON_ERROR), 'attr') ?>,
            token: <?= esc(json_encode($tokenValue, JSON_THROW_ON_ERROR), 'attr') ?>,
            sectionLabels: <?= esc(json_encode($sectionLabels, JSON_THROW_ON_ERROR), 'attr') ?>,
            messages: <?= esc(json_encode($messages, JSON_THROW_ON_ERROR), 'attr') ?>
        })"
        x-on:input="scheduleSave()"
        x-on:change="scheduleSave()"
        class="space-y-8"
    >
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('CvBuilder.title')) ?></p>
                <h1 class="mt-2 break-words text-2xl font-semibold tracking-tight text-ink sm:text-3xl"><?= esc($state['cv']['title']) ?></h1>
                <p class="mt-2 text-sm text-muted"><?= esc(lang('CvBuilder.description')) ?></p>
            </div>
            <a class="inline-flex min-h-10 items-center rounded-md border border-line bg-paper px-3 text-sm font-medium text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('CvBuilder.back')) ?></a>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
            <aside class="min-w-0 rounded-md border border-line bg-paper p-3 lg:sticky lg:top-4">
                <div class="flex items-baseline justify-between px-2 pb-3">
                    <h2 class="text-sm font-semibold text-ink"><?= esc(lang('CvBuilder.steps_title')) ?></h2>
                    <span class="text-xs text-muted"><?= esc(lang('CvBuilder.step_progress', [$step, 8])) ?></span>
                </div>
                <nav aria-label="<?= esc(lang('CvBuilder.title')) ?>">
                    <ol class="flex snap-x gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible lg:pb-0">
                        <?php foreach (lang('CvBuilder.steps') as $index => $stepName): ?>
                            <li class="min-w-40 snap-start lg:min-w-0">
                                <button
                                    class="flex min-h-11 w-full items-center gap-3 rounded-md border px-3 py-2 text-left text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                                    x-bind:class="step === <?= $index + 1 ?> ? 'border-accent-700 bg-accent-50 text-accent-900' : 'border-transparent text-muted hover:border-line hover:bg-canvas'"
                                    type="button"
                                    x-on:click="navigate(<?= $index + 1 ?>)"
                                    x-bind:aria-current="step === <?= $index + 1 ?> ? 'step' : null"
                                >
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-sm border text-xs" x-bind:class="step === <?= $index + 1 ?> ? 'border-accent-700 bg-paper font-semibold' : 'border-line bg-canvas'"><?= $index + 1 ?></span>
                                    <span><?= esc($stepName) ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <p class="mt-3 min-h-12 border-t border-line px-2 pt-3 text-xs leading-5 text-muted" aria-live="polite" role="status"
                    x-bind:class="status === 'error' ? 'text-red-800' : (status === 'saved' ? 'text-accent-900' : '')">
                    <span><?= esc(lang('CvBuilder.save_status')) ?>:</span>
                    <span x-text="status === 'saving' ? '<?= esc(lang('CvBuilder.saving'), 'js') ?>' : (status === 'saved' ? '<?= esc(lang('CvBuilder.saved'), 'js') ?>' : (status === 'error' ? '<?= esc(lang('CvBuilder.save_error'), 'js') ?>' : ''))"></span>
                </p>
            </aside>

            <div class="min-w-0 space-y-5">
                <?php for ($stepNumber = 1; $stepNumber <= 8; $stepNumber++): ?>
                    <section
                        id="builder-step-<?= $stepNumber ?>"
                        <?= $stepNumber === $step ? '' : 'hidden' ?>
                        x-bind:hidden="step !== <?= $stepNumber ?>"
                        class="min-w-0 rounded-md border border-line bg-paper p-5 sm:p-7"
                    >
                        <?= $this->include('builder/steps/step' . $stepNumber) ?>
                    </section>
                <?php endfor; ?>

                <div class="flex justify-between gap-3">
                    <button
                        class="min-h-11 rounded-md border border-line bg-paper px-4 text-sm font-medium text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 disabled:cursor-not-allowed disabled:opacity-50"
                        type="button"
                        x-on:click="navigate(step - 1)"
                        x-bind:disabled="step <= 1 || busy"
                    ><?= esc(lang('CvBuilder.previous')) ?></button>
                    <button
                        class="min-h-11 rounded-md bg-accent-700 px-4 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60"
                        type="button"
                        x-on:click="navigate(step + 1)"
                        x-bind:disabled="step >= 8 || busy"
                    ><?= esc(lang('CvBuilder.next')) ?></button>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
