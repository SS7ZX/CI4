<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <nav class="mb-5 text-sm" aria-label="<?= esc(lang('Jobs.breadcrumb')) ?>">
        <a class="font-medium text-accent-800 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.title')) ?></a>
        <span aria-hidden="true"> / </span>
        <span><?= esc(lang('Jobs.find_jobs')) ?></span>
    </nav>

    <section class="max-w-2xl rounded-md border border-neutral-200 bg-white p-5 sm:p-7">
        <h1 class="text-2xl font-semibold"><?= esc(lang('Jobs.consent_title')) ?></h1>
        <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Jobs.consent_intro', [$cv->title])) ?></p>

        <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-neutral-700">
            <li><?= esc(lang('Jobs.consent_data_scope')) ?></li>
            <li><?= esc(lang('Jobs.consent_no_personal_data')) ?></li>
            <li><?= esc(lang('Jobs.consent_withdrawal')) ?></li>
        </ul>

        <form class="mt-6 space-y-4" action="<?= esc($consentUrl) ?>" method="post">
            <?= csrf_field() ?>
            <label class="flex items-start gap-3 text-sm font-medium">
                <input class="mt-1 size-4 accent-accent-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="checkbox" name="consent" value="1" required>
                <span><?= esc(lang('Jobs.consent_checkbox')) ?></span>
            </label>
            <div class="flex flex-wrap gap-3">
                <button class="min-h-11 rounded-md bg-accent-800 px-4 font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Jobs.consent_continue')) ?></button>
                <a class="inline-flex min-h-11 items-center rounded-md border border-neutral-400 px-4 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Jobs.cancel')) ?></a>
            </div>
        </form>
    </section>
<?= $this->endSection() ?>
