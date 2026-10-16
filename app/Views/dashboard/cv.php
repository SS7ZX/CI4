<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="rounded-md border border-neutral-200 bg-white p-5 sm:p-7">
        <h1 class="text-2xl font-semibold"><?= esc($cv->title) ?></h1>
        <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('Dashboard.builder_later')) ?></p>
        <a class="mt-5 inline-flex min-h-10 items-center rounded-md border border-neutral-400 px-3 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.backToDashboard')) ?></a>
    </section>
<?= $this->endSection() ?>
