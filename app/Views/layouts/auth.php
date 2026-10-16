<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <section class="mx-auto w-full max-w-md">
        <?= $this->renderSection('main') ?>
    </section>
<?= $this->endSection() ?>
