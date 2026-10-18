<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold"><?= esc(lang('Dashboard.title')) ?></h1>
            <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('Dashboard.description')) ?></p>
        </div>
        <form action="<?= esc(url_to('cv-create')) ?>" method="post" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <?= csrf_field() ?>
            <label class="sr-only" for="new-cv-title"><?= esc(lang('Dashboard.new_cv_title')) ?></label>
            <input class="min-h-11 rounded-md border border-neutral-400 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="new-cv-title" name="title" type="text" maxlength="160" placeholder="<?= esc(lang('Dashboard.new_cv_title')) ?>" required>
            <button class="min-h-11 rounded-md bg-accent-700 px-4 font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Dashboard.create')) ?></button>
        </form>
    </div>

    <?php if (is_array(session('errors'))): ?>
        <ul class="mb-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <?php foreach (session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($cvs === []): ?>
        <section class="rounded-md border border-neutral-200 bg-white px-5 py-10 text-center">
            <h2 class="text-lg font-semibold"><?= esc(lang('Dashboard.empty_title')) ?></h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-neutral-700"><?= esc(lang('Dashboard.empty_description')) ?></p>
        </section>
    <?php else: ?>
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($cvs as $cv): ?>
                <li class="flex min-h-48 flex-col rounded-md border border-neutral-200 bg-white p-5">
                    <h2 class="text-lg font-semibold"><?= esc($cv->title) ?></h2>
                    <p class="mt-1 text-sm text-neutral-700"><?= esc(lang('Dashboard.updated')) ?>: <?= esc((string) $cv->updated_at) ?></p>
                    <div class="mt-auto flex flex-wrap gap-2 pt-5">
                        <a class="inline-flex min-h-10 items-center rounded-md border border-neutral-400 px-3 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-show', $cv->id)) ?>"><?= esc(lang('Dashboard.open')) ?></a>
                        <form action="<?= esc(url_to('cv-duplicate', $cv->id)) ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit"><?= esc(lang('Dashboard.duplicate')) ?></button>
                        </form>
                        <form action="<?= esc(url_to('cv-delete', $cv->id)) ?>" method="post" onsubmit="return window.confirm('<?= esc(lang('Dashboard.confirm_delete'), 'js') ?>')">
                            <?= csrf_field() ?>
                            <button class="min-h-10 rounded-md border border-red-700 px-3 text-sm font-medium text-red-800 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="submit"><?= esc(lang('Dashboard.delete')) ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?= $this->endSection() ?>
