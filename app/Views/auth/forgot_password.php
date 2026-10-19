<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= esc(lang('Auth.forgotPassword')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Auth.form_kicker')) ?></p>
        <h1 class="mb-2 mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Auth.forgotPassword')) ?></h1>
        <p class="mb-6 text-sm leading-6 text-muted"><?= esc(lang('Auth.forgotPasswordDescription')) ?></p>
        <?php if (session('error') !== null): ?>
            <p class="mb-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert"><?= esc(session('error')) ?></p>
        <?php endif; ?>
        <?php if (is_array(session('errors'))): ?>
            <ul class="mb-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?php foreach (session('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form action="<?= esc(url_to('magic-link')) ?>" method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label for="email" class="mb-1 block text-sm font-medium"><?= esc(lang('Auth.email')) ?></label>
                <input class="min-h-12 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="email" name="email" type="email" autocomplete="email" value="<?= esc(old('email')) ?>" required>
            </div>
            <button class="min-h-12 w-full rounded-md bg-accent-700 px-4 font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Auth.send')) ?></button>
        </form>
        <p class="mt-5 text-sm"><a class="font-medium text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700" href="<?= esc(url_to('login')) ?>"><?= esc(lang('Auth.backToLogin')) ?></a></p>
    </div>
<?= $this->endSection() ?>
