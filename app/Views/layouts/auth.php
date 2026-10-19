<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <section class="mx-auto grid w-full max-w-5xl overflow-hidden rounded-md border border-line bg-paper md:grid-cols-[minmax(0,0.9fr)_minmax(22rem,1.1fr)]">
        <aside class="hidden flex-col justify-between border-r border-line bg-[#eeeee7] p-8 md:flex lg:p-10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Auth.workspace_kicker')) ?></p>
                <h2 class="mt-5 max-w-sm text-3xl font-semibold leading-tight tracking-tight text-ink"><?= esc(lang('Auth.workspace_title')) ?></h2>
                <p class="mt-4 max-w-sm text-sm leading-6 text-muted"><?= esc(lang('Auth.workspace_description')) ?></p>
            </div>
            <ul class="mt-10 space-y-3 border-t border-line pt-5 text-sm text-muted">
                <?php foreach (lang('Auth.workspace_items') as $item): ?>
                    <li class="flex gap-3">
                        <span class="mt-2 h-1.5 w-1.5 shrink-0 bg-accent-700" aria-hidden="true"></span>
                        <span><?= esc($item) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="mt-10 text-sm font-semibold text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700" href="<?= esc(site_url('/')) ?>"><?= esc(lang('Auth.backToCvMaker')) ?></a>
        </aside>
        <div class="p-5 sm:p-8 lg:p-10">
            <a class="mb-6 inline-flex text-sm font-semibold text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700 md:hidden" href="<?= esc(site_url('/')) ?>"><?= esc(lang('Auth.backToCvMaker')) ?></a>
            <?= $this->renderSection('main') ?>
        </div>
    </section>
<?= $this->endSection() ?>
