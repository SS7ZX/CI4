<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= esc(lang('Auth.login')) ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent-900"><?= esc(lang('Auth.form_kicker')) ?></p>
        <h1 class="mb-2 mt-2 text-3xl font-semibold tracking-tight text-ink"><?= esc(lang('Auth.login')) ?></h1>
        <p class="mb-6 text-sm leading-6 text-muted"><?= esc(lang('Auth.loginDescription')) ?></p>

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
        <?php if (session('message') !== null): ?>
            <p class="mb-4 rounded-md border border-green-700 bg-green-50 px-4 py-3 text-sm text-green-900" role="status"><?= esc(session('message')) ?></p>
        <?php endif; ?>

        <form action="<?= esc(url_to('login')) ?>" method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label for="email" class="mb-1 block text-sm font-medium"><?= esc(lang('Auth.email')) ?></label>
                <input class="min-h-12 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="email" name="email" type="email" autocomplete="email" value="<?= esc(old('email')) ?>" required>
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-medium"><?= esc(lang('Auth.password')) ?></label>
                <input class="min-h-12 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                <label class="flex items-center gap-2 text-sm">
                    <input class="size-4 accent-accent-700" type="checkbox" name="remember" value="1" <?= old('remember') ? 'checked' : '' ?>>
                    <?= esc(lang('Auth.rememberMe')) ?>
                </label>
            <?php endif; ?>
            <button class="min-h-12 w-full rounded-md bg-accent-700 px-4 font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Auth.login')) ?></button>
        </form>

        <div class="mt-5 space-y-2 text-sm">
            <p><a class="font-medium text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700" href="<?= esc(url_to('magic-link')) ?>"><?= esc(lang('Auth.forgotPassword')) ?></a></p>
            <?php if (setting('Auth.allowRegistration')): ?>
                <p class="text-muted"><?= esc(lang('Auth.needAccount')) ?> <a class="font-medium text-accent-900 underline decoration-accent-700 underline-offset-4 hover:text-accent-700" href="<?= esc(url_to('register')) ?>"><?= esc(lang('Auth.register')) ?></a></p>
            <?php endif; ?>
        </div>
    </div>
<?= $this->endSection() ?>
