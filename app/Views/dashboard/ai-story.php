<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="mx-auto max-w-3xl rounded-md border border-line bg-paper p-5 sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.ai_story_page_title')) ?></h1>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_story_page_description')) ?></p>
        <?= view('dashboard/ai-progress', ['currentStep' => 2]) ?>
        <?php if ($errors !== []): ?>
            <ul class="mt-4 rounded-md border border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form action="<?= esc(same_origin_url((string) url_to('cv-ai-consent'))) ?>" method="post" class="mt-5 grid gap-4">
            <?= csrf_field() ?>
            <?php foreach ($profile as $field => $value): ?>
                <input type="hidden" name="<?= esc($field) ?>" value="<?= esc($value) ?>">
            <?php endforeach; ?>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-story"><?= esc(lang('Dashboard.ai_story')) ?></label>
                <textarea class="min-h-64 w-full rounded-md border border-line bg-canvas p-3 text-sm leading-6 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-story" name="story" maxlength="8000" required><?= esc($story) ?></textarea>
                <p class="mt-1 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_story_help')) ?></p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900" type="submit"><?= esc(lang('Dashboard.ai_continue')) ?></button>
                <a class="inline-flex min-h-11 items-center text-sm font-medium text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('cv-ai-start')) ?>"><?= esc(lang('Dashboard.ai_back')) ?></a>
            </div>
        </form>
    </section>
<?= $this->endSection() ?>
