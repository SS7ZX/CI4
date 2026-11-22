<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= esc(lang('Auth.forgotPassword')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Auth.form_kicker')) ?></p>
        <h1 class="mb-3 mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Auth.checkYourEmail')) ?></h1>
        <p class="mb-5 text-sm leading-6 text-muted"><?= esc(lang('Auth.passwordResetDetails')) ?></p>
        <p class="text-sm leading-6 text-muted"><?= esc(lang('Auth.forgotPasswordNextStep')) ?></p>
        <?php if (isset($developmentResetUrl) && is_string($developmentResetUrl) && $developmentResetUrl !== ''): ?>
            <div class="mt-5 rounded-md border border-amber-700 bg-amber-50 p-4" role="status">
                <p class="text-sm font-semibold text-amber-900">Mode pengembangan. SMTP belum dikonfigurasi.</p>
                <a class="mt-2 inline-flex min-h-10 items-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900" href="<?= esc($developmentResetUrl) ?>">Atur kata sandi dengan tautan pengembangan</a>
            </div>
        <?php endif; ?>
        <p class="mt-5 text-sm"><a class="font-medium text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700" href="<?= esc(url_to('login')) ?>"><?= esc(lang('Auth.backToLogin')) ?></a></p>
    </div>
<?= $this->endSection() ?>
