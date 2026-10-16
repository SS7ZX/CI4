<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= esc(lang('Auth.forgotPassword')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div class="rounded-md border border-neutral-200 bg-white p-5 sm:p-7">
        <h1 class="mb-3 text-2xl font-semibold"><?= esc(lang('Auth.checkYourEmail')) ?></h1>
        <p class="mb-5 text-sm text-neutral-700"><?= esc(lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60])) ?></p>
        <p class="text-sm text-neutral-700"><?= esc(lang('Auth.forgotPasswordNextStep')) ?></p>
        <p class="mt-5 text-sm"><a class="font-medium text-accent-700 underline" href="<?= esc(url_to('login')) ?>"><?= esc(lang('Auth.backToLogin')) ?></a></p>
    </div>
<?= $this->endSection() ?>
