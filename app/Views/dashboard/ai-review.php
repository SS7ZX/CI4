<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="ai-wizard-page mx-auto max-w-5xl rounded-md border border-line bg-paper p-5 sm:p-8" data-reveal data-ai-wizard-page data-ai-step="5">
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= esc(lang('Dashboard.ai_review_title')) ?></h1>
        <p class="mt-2 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_review_description')) ?></p>
        <?= view('dashboard/ai-progress', ['currentStep' => 5]) ?>
        <p class="mt-3 rounded-md border border-line bg-canvas px-4 py-3 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_review_warning')) ?></p>
        <?php if ($message !== ''): ?>
            <p class="mt-3 rounded-md border border-green-700 bg-green-50 px-4 py-3 text-sm leading-6 text-green-950" role="status"><?= esc($message) ?></p>
        <?php endif; ?>

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
            x-data="{ pendingAction: '', summaryLength: <?= mb_strlen($draft['summary'], 'UTF-8') ?>, instructionLength: <?= mb_strlen($summaryInstruction, 'UTF-8') ?>, factsLength: <?= mb_strlen($additionalFacts, 'UTF-8') ?> }"
            x-on:submit="pendingAction = $event.submitter ? $event.submitter.value : 'save'"
            x-bind:aria-busy="(pendingAction !== '').toString()"
        >
            <?= csrf_field() ?>
            <input type="hidden" name="sanitized_story" value="<?= esc($sanitizedStory) ?>">
            <h2 class="border-b border-line pb-2 text-base font-semibold text-ink sm:col-span-2"><?= esc(lang('Dashboard.ai_review_profile_section')) ?></h2>
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
                <h2 class="mb-3 border-b border-line pb-2 text-base font-semibold text-ink"><?= esc(lang('Dashboard.ai_review_summary_section')) ?></h2>
                <label class="mb-1 block text-sm font-medium" for="ai-review-summary"><?= esc(lang('Dashboard.ai_summary')) ?></label>
                <?php if (trim($draft['summary']) === ''): ?>
                    <p class="mb-2 rounded-md border border-amber-700 bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-950" role="status"><?= esc(lang('Dashboard.ai_summary_not_generated')) ?></p>
                <?php endif; ?>
                <p class="mb-2 text-sm leading-6 text-muted"><?= esc(lang('CvBuilder.summary_guidance')) ?></p>
                <textarea class="min-h-32 w-full rounded-md border border-line bg-canvas p-3 text-sm leading-6 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-summary" name="summary" maxlength="3000" aria-describedby="ai-review-summary-counter" x-on:input="summaryLength = Array.from($event.target.value).length"><?= esc($draft['summary']) ?></textarea>
                <p class="mt-1 text-xs text-muted" id="ai-review-summary-counter"><span x-text="summaryLength"><?= mb_strlen($draft['summary'], 'UTF-8') ?></span> <?= esc(lang('Dashboard.ai_summary_character_limit')) ?></p>
                <section class="mt-4 rounded-md border border-line bg-canvas p-4" aria-labelledby="ai-summary-refine-title">
                    <h2 class="text-base font-semibold text-ink" id="ai-summary-refine-title"><?= esc(lang('Dashboard.ai_summary_refine_title')) ?></h2>
                    <p class="mt-1 text-sm leading-6 text-muted"><?= esc(lang('Dashboard.ai_summary_refine_help')) ?></p>
                    <label class="mb-1 mt-4 block text-sm font-medium" for="ai-summary-instruction"><?= esc(lang('Dashboard.ai_refine_instruction')) ?></label>
                    <textarea class="min-h-20 w-full rounded-md border border-line bg-paper p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-summary-instruction" name="summary_instruction" maxlength="500" aria-describedby="ai-summary-instruction-help ai-summary-instruction-counter" x-on:input="instructionLength = Array.from($event.target.value).length"><?= esc($summaryInstruction) ?></textarea>
                    <p class="mt-1 flex justify-between gap-3 text-xs text-muted" id="ai-summary-instruction-help">
                        <span><?= esc(lang('Dashboard.ai_refine_instruction_help')) ?></span>
                        <span id="ai-summary-instruction-counter"><span x-text="instructionLength"><?= mb_strlen($summaryInstruction, 'UTF-8') ?></span> <?= esc(lang('Dashboard.ai_instruction_character_limit')) ?></span>
                    </p>
                    <?php if (isset($errors['summary_instruction'])): ?>
                        <p class="mt-1 text-sm text-red-800" role="alert"><?= esc($errors['summary_instruction']) ?></p>
                    <?php endif; ?>
                    <label class="mb-1 mt-4 block text-sm font-medium" for="ai-summary-facts"><?= esc(lang('Dashboard.ai_refine_facts')) ?></label>
                    <textarea class="min-h-20 w-full rounded-md border border-line bg-paper p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-summary-facts" name="additional_facts" maxlength="2000" aria-describedby="ai-summary-facts-help ai-summary-facts-counter" x-on:input="factsLength = Array.from($event.target.value).length"><?= esc($additionalFacts) ?></textarea>
                    <p class="mt-1 flex justify-between gap-3 text-xs text-muted" id="ai-summary-facts-help">
                        <span><?= esc(lang('Dashboard.ai_refine_facts_help')) ?></span>
                        <span id="ai-summary-facts-counter"><span x-text="factsLength"><?= mb_strlen($additionalFacts, 'UTF-8') ?></span> <?= esc(lang('Dashboard.ai_facts_character_limit')) ?></span>
                    </p>
                    <?php if (isset($errors['additional_facts'])): ?>
                        <p class="mt-1 text-sm text-red-800" role="alert"><?= esc($errors['additional_facts']) ?></p>
                    <?php endif; ?>
                    <label class="mt-4 flex items-start gap-3 text-sm leading-6 text-ink">
                        <input class="mt-1 size-4 accent-accent-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="checkbox" name="ai_consent" value="1">
                        <span><?= esc(lang('Dashboard.ai_refine_consent')) ?></span>
                    </label>
                    <?php if (isset($errors['ai_consent'])): ?>
                        <p class="mt-1 text-sm text-red-800" role="alert"><?= esc($errors['ai_consent']) ?></p>
                    <?php endif; ?>
                    <button class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md border border-accent-800 bg-paper px-4 text-sm font-semibold text-accent-900 hover:bg-accent-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 disabled:cursor-wait disabled:opacity-60" type="submit" name="action" value="refine" formaction="<?= esc(same_origin_url((string) url_to('cv-ai-summary-refine'))) ?>" x-bind:disabled="pendingAction !== ''">
                        <span x-show="pendingAction !== 'refine'"><?= esc(lang('Dashboard.ai_refine_button')) ?></span>
                        <span x-cloak x-show="pendingAction === 'refine'"><?= esc(lang('Dashboard.ai_refining')) ?></span>
                    </button>
                </section>
            </div>
            <h2 class="border-b border-line pb-2 text-base font-semibold text-ink sm:col-span-2"><?= esc(lang('Dashboard.ai_review_experience_section')) ?></h2>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-experience"><?= esc(lang('Dashboard.ai_experience')) ?></label>
                <textarea class="min-h-32 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-experience" name="experience" maxlength="3000"><?= esc($draft['experience']) ?></textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ai-review-education"><?= esc(lang('Dashboard.ai_education')) ?></label>
                <textarea class="min-h-32 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-education" name="education" maxlength="3000"><?= esc($draft['education']) ?></textarea>
            </div>
            <h2 class="border-b border-line pb-2 text-base font-semibold text-ink sm:col-span-2"><?= esc(lang('Dashboard.ai_review_skills_section')) ?></h2>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium" for="ai-review-skills"><?= esc(lang('Dashboard.ai_skills')) ?></label>
                <textarea class="min-h-20 w-full rounded-md border border-line bg-canvas p-3 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="ai-review-skills" name="skills" maxlength="3000"><?= esc($draft['skills']) ?></textarea>
            </div>
            <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-5 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="submit" name="action" value="save" data-ai-direction="forward" x-bind:disabled="pendingAction !== ''">
                    <span x-show="pendingAction !== 'save'"><?= esc(lang('Dashboard.ai_save')) ?></span>
                    <span x-cloak x-show="pendingAction === 'save'"><?= esc(lang('Dashboard.ai_saving')) ?></span>
                </button>
                <a class="inline-flex min-h-11 items-center text-sm font-medium text-accent-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" href="<?= esc(url_to('dashboard')) ?>"><?= esc(lang('Dashboard.ai_back')) ?></a>
            </div>
        </form>
    </section>
<?= $this->endSection() ?>
