<h2 class="mb-5 text-xl font-semibold"><?= esc(lang('CvBuilder.steps')[1]) ?></h2>
<div>
    <label class="mb-1 block text-sm font-medium" for="profile-summary"><?= esc(lang('CvBuilder.summary_label')) ?></label>
    <textarea class="min-h-48 w-full rounded-md border border-neutral-400 p-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="profile-summary" maxlength="3000" placeholder="<?= esc(lang('CvBuilder.summary_help')) ?>" x-model="state.profile.summary"></textarea>
    <p class="mt-1 text-sm text-neutral-700"><span x-text="(state.profile.summary ?? '').length"></span>/3000</p>
    <p class="mt-1 text-sm text-red-800" x-show="errors.summary" x-text="errors.summary" role="alert"></p>
</div>
