<h2 class="text-xl font-semibold tracking-tight text-ink"><?= esc(lang('CvBuilder.steps')[1]) ?></h2>
<p class="mb-5 mt-1 text-sm leading-6 text-muted"><?= esc(lang('CvBuilder.step_2_description')) ?></p>
<div>
    <label class="mb-1 block text-sm font-medium" for="profile-summary"><?= esc(lang('CvBuilder.summary_label')) ?></label>
    <p class="mb-3 text-sm leading-6 text-muted"><?= esc(lang('CvBuilder.summary_guidance')) ?></p>
    <textarea class="min-h-48 w-full rounded-md border border-line p-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="profile-summary" maxlength="3000" placeholder="<?= esc(lang('CvBuilder.summary_help')) ?>" x-model="state.profile.summary"></textarea>
    <p class="mt-1 text-sm text-muted"><span x-text="(state.profile.summary ?? '').length"></span>/3000</p>
    <p class="mt-1 text-sm text-red-800" x-show="errors.summary" x-text="errors.summary" role="alert"></p>
</div>
