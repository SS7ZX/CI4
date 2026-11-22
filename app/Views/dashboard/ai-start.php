<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="mx-auto max-w-3xl rounded-md border border-line bg-paper p-5 sm:p-8" data-reveal>
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.ai_start_title')) ?></h1>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_start_description')) ?></p>
        <?= view('dashboard/ai-progress', ['currentStep' => 1]) ?>
        <?php if (! $enabled): ?>
            <p class="mt-5 rounded-md border border-amber-700 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950" role="status"><?= esc(lang('Dashboard.ai_disabled')) ?></p>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <ul class="mt-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form action="<?= esc(same_origin_url((string) url_to('cv-ai-story'))) ?>" method="post" class="mt-5 grid gap-4">
            <?= csrf_field() ?>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-profile-name"><?= esc(lang('Dashboard.ai_full_name')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-profile-name" name="full_name" type="text" maxlength="160" autocomplete="name" value="<?= esc($profile['full_name']) ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-profile-target"><?= esc(lang('Dashboard.ai_target_role')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-profile-target" name="target_role" type="text" maxlength="160" value="<?= esc($profile['target_role']) ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-profile-email"><?= esc(lang('Dashboard.ai_email')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-profile-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= esc($profile['email']) ?>">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-profile-phone"><?= esc(lang('Dashboard.ai_phone')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-profile-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="<?= esc($profile['phone']) ?>">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-profile-city"><?= esc(lang('Dashboard.ai_city')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-profile-city" name="city" type="text" maxlength="120" autocomplete="address-level2" value="<?= esc($profile['city']) ?>">
            </div>
            <div class="flex flex-wrap gap-3">
                <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-not-allowed disabled:opacity-60" type="submit" <?= $enabled ? '' : 'disabled' ?>><?= esc(lang('Dashboard.ai_continue')) ?></button>
                <a class="inline-flex min-h-11 items-center text-sm font-medium text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.ai_back')) ?></a>
            </div>
        </form>
    </section>
<?= $this->endSection() ?>
