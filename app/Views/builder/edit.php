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
            tokenName: <?= esc(json_encode($tokenName, JSON_THROW_ON_ERROR), 'attr') ?>,
            token: <?= esc(json_encode($tokenValue, JSON_THROW_ON_ERROR), 'attr') ?>,
            sectionLabels: <?= esc(json_encode($sectionLabels, JSON_THROW_ON_ERROR), 'attr') ?>
        })"
        x-on:input="scheduleSave()"
        x-on:change="scheduleSave()"
        class="space-y-6"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold"><?= esc(lang('CvBuilder.title')) ?></h1>
                <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('CvBuilder.description')) ?></p>
            </div>
            <a class="inline-flex min-h-10 items-center rounded-md border border-neutral-400 px-3 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('CvBuilder.back')) ?></a>
        </div>

        <nav aria-label="<?= esc(lang('CvBuilder.title')) ?>" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            <?php foreach (lang('CvBuilder.steps') as $index => $stepName): ?>
                <button
                    class="min-h-11 rounded-md border px-3 text-left text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                    x-bind:class="step === <?= $index + 1 ?> ? 'border-accent-700 bg-accent-50 text-accent-900' : 'border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-100'"
                    type="button"
                    x-on:click="navigate(<?= $index + 1 ?>)"
                    x-bind:aria-current="step === <?= $index + 1 ?> ? 'step' : null"
                >
                    <span class="mr-1"><?= $index + 1 ?>.</span><?= esc($stepName) ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <p class="min-h-6 text-sm text-neutral-700" aria-live="polite" role="status">
            <span><?= esc(lang('CvBuilder.save_status')) ?>:</span>
            <span x-text="status === 'saving' ? '<?= esc(lang('CvBuilder.saving'), 'js') ?>' : (status === 'saved' ? '<?= esc(lang('CvBuilder.saved'), 'js') ?>' : (status === 'error' ? '<?= esc(lang('CvBuilder.save_error'), 'js') ?>' : ''))"></span>
        </p>

        <?php for ($stepNumber = 1; $stepNumber <= 6; $stepNumber++): ?>
            <section x-cloak x-show="step === <?= $stepNumber ?>" class="rounded-md border border-neutral-200 bg-white p-5 sm:p-7">
                <?= $this->include('builder/steps/step' . $stepNumber) ?>
            </section>
        <?php endfor; ?>

        <div class="flex justify-between gap-3">
            <button
                class="min-h-11 rounded-md border border-neutral-400 px-4 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 disabled:cursor-not-allowed disabled:opacity-50"
                type="button"
                x-on:click="navigate(step - 1)"
                x-bind:disabled="step <= 1 || busy"
            ><?= esc(lang('CvBuilder.previous')) ?></button>
            <button
                class="min-h-11 rounded-md bg-accent-700 px-4 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60"
                type="button"
                x-on:click="navigate(step + 1)"
                x-bind:disabled="step >= 6 || busy"
            ><?= esc(lang('CvBuilder.next')) ?></button>
        </div>
    </div>
<?= $this->endSection() ?>
