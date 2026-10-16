<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="rounded-lg border border-neutral-200 bg-white p-6 sm:p-8">
    <p class="text-sm font-semibold text-accent-700"><?= esc(lang('App.home_ready')) ?></p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-900">
        <?= esc(lang('App.home_title')) ?>
    </h1>
    <p class="mt-3 max-w-2xl text-base leading-7 text-neutral-700">
        <?= esc(lang('App.home_description')) ?>
    </p>
    <p class="mt-6 border-t border-neutral-200 pt-4 text-sm text-neutral-600">
        <?= esc(lang('App.home_setup')) ?>
    </p>
</section>
<?= $this->endSection() ?>
