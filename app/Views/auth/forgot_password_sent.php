<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= esc(lang('Auth.forgotPassword')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Auth.form_kicker')) ?></p>
        <h1 class="mb-3 mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Auth.checkYourEmail')) ?></h1>
        <p class="mb-5 text-sm leading-6 text-muted"><?= esc(lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60])) ?></p>
        <p class="text-sm leading-6 text-muted"><?= esc(lang('Auth.forgotPasswordNextStep')) ?></p>
        <p class="mt-5 text-sm"><a class="font-medium text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700" href="<?= esc(url_to('login')) ?>"><?= esc(lang('Auth.backToLogin')) ?></a></p>
    </div>
<?= $this->endSection() ?>
