<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="mx-auto max-w-4xl rounded-md border border-line bg-paper p-5 sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.ai_review_title')) ?></h1>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_review_description')) ?></p>
        <p class="mt-3 rounded-md border border-line bg-canvas px-4 py-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_review_warning')) ?></p>

        <?php if ($errors !== []): ?>
            <ul class="mt-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?php foreach ($errors as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form
            action="<?= esc(same_origin_url((string) url_to('cv-ai-save'))) ?>"
            method="post"
            class="mt-5 grid gap-4 sm:grid-cols-2"
            x-data="{ pending: false }"
            x-on:submit="pending = true"
            x-bind:aria-busy="pending.toString()"
        >
            <?= csrf_field() ?>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-name"><?= esc(lang('Dashboard.ai_full_name')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-name" name="full_name" type="text" maxlength="160" value="<?= esc($draft['full_name']) ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-headline"><?= esc(lang('Dashboard.ai_headline')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-headline" name="headline" type="text" maxlength="160" value="<?= esc($draft['headline']) ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-email"><?= esc(lang('Dashboard.ai_email')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-email" name="email" type="email" maxlength="254" value="<?= esc($draft['email']) ?>">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-phone"><?= esc(lang('Dashboard.ai_phone')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-phone" name="phone" type="tel" maxlength="40" value="<?= esc($draft['phone']) ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium" for="ai-review-city"><?= esc(lang('Dashboard.ai_city')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-line bg-canvas px-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-city" name="city" type="text" maxlength="120" value="<?= esc($draft['city']) ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium" for="ai-review-summary"><?= esc(lang('Dashboard.ai_summary')) ?></label>
                <textarea class="min-h-24 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-summary" name="summary" maxlength="3000"><?= esc($draft['summary']) ?></textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-experience"><?= esc(lang('Dashboard.ai_experience')) ?></label>
                <textarea class="min-h-32 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-experience" name="experience" maxlength="3000"><?= esc($draft['experience']) ?></textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-education"><?= esc(lang('Dashboard.ai_education')) ?></label>
                <textarea class="min-h-32 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-education" name="education" maxlength="3000"><?= esc($draft['education']) ?></textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium" for="ai-review-skills"><?= esc(lang('Dashboard.ai_skills')) ?></label>
                <textarea class="min-h-20 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-skills" name="skills" maxlength="3000"><?= esc($draft['skills']) ?></textarea>
            </div>
            <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="submit" x-bind:disabled="pending">
                    <span x-show="!pending"><?= esc(lang('Dashboard.ai_save')) ?></span>
                    <span x-cloak x-show="pending"><?= esc(lang('Dashboard.ai_saving')) ?></span>
                </button>
                <a class="inline-flex min-h-11 items-center text-sm font-medium text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.ai_back')) ?></a>
            </div>
        </form>
    </section>
<?= $this->endSection() ?>
