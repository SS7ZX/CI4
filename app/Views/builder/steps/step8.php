<?php
$currentTheme = null;
foreach ($themes as $theme) {
    if ($theme['key'] === ($state['cv']['theme_key'] ?? '')) {
        $currentTheme = $theme;
        break;
    }
}
?>

<h2 class="mb-2 text-xl font-semibold tracking-tight text-ink"><?= esc(lang('CvBuilder.steps')[7]) ?></h2>
<p class="mb-5 max-w-2xl text-sm leading-6 text-muted"><?= esc(lang('CvBuilder.pdf_review_help')) ?></p>

<div class="grid items-start gap-6 xl:grid-cols-[15rem_minmax(0,1fr)]">
    <div class="space-y-5 rounded-md border border-line bg-canvas p-4 sm:p-5">
        <div>
            <label for="paper-size" class="mb-1 block text-sm font-semibold text-ink"><?= esc(lang('CvBuilder.paper_size')) ?></label>
            <select id="paper-size" class="min-h-11 w-full rounded-md border border-neutral-400 bg-paper px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" x-model="state.cv.paper_size">
                <option value="a4"><?= esc(lang('CvBuilder.paper_a4')) ?></option>
                <option value="letter"><?= esc(lang('CvBuilder.paper_letter')) ?></option>
            </select>
            <p class="mt-1 text-sm text-red-800" x-show="errors.paper_size" x-text="errors.paper_size" role="alert"></p>
        </div>
        <div>
            <label for="font-scale" class="mb-1 block text-sm font-semibold text-ink"><?= esc(lang('CvBuilder.font_scale')) ?></label>
            <select id="font-scale" class="min-h-11 w-full rounded-md border border-neutral-400 bg-paper px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" x-model="state.cv.font_scale">
                <option value="small"><?= esc(lang('CvBuilder.font_small')) ?></option>
                <option value="medium"><?= esc(lang('CvBuilder.font_medium')) ?></option>
                <option value="large"><?= esc(lang('CvBuilder.font_large')) ?></option>
            </select>
            <p class="mt-1 text-sm text-red-800" x-show="errors.font_scale" x-text="errors.font_scale" role="alert"></p>
        </div>
        <div class="border-t border-line pt-4">
            <h3 class="text-sm font-semibold text-ink"><?= esc(lang('Themes.current_theme')) ?></h3>
            <?php if ($currentTheme !== null): ?>
                <p class="mt-1 text-sm font-medium text-ink"><?= esc(lang($currentTheme['name_key'])) ?></p>
                <p class="mt-2 inline-flex rounded-md border border-line px-2 py-1 text-xs font-semibold text-ink">
                    <?= esc(lang($currentTheme['ats_friendly'] ? 'Themes.ats_badge' : 'Themes.non_ats_badge')) ?>
                </p>
                <p class="mt-2 text-xs leading-5 text-muted">
                    <?= esc(lang($currentTheme['ats_friendly'] ? 'Themes.ats_scope_note' : 'Themes.ats_visual_warning')) ?>
                </p>
            <?php else: ?>
                <p class="mt-2 text-sm text-red-800" role="alert"><?= esc(lang('Themes.invalid_theme')) ?></p>
            <?php endif; ?>
            <button class="mt-3 min-h-10 w-full rounded-md border border-line bg-paper px-3 text-sm font-medium text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700 disabled:cursor-wait disabled:opacity-60" type="button" x-on:click="navigate(7)" x-bind:disabled="busy">
                <?= esc(lang('Themes.change_theme')) ?>
            </button>
        </div>
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-ink"><?= esc(lang('CvBuilder.accent_color')) ?></legend>
            <div class="flex flex-wrap gap-2">
                <template x-for="color in selectedPalette()" x-bind:key="color">
                    <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-md border border-line bg-paper px-2.5 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent-700">
                        <input class="size-4 accent-accent-700" type="radio" name="accent_color" x-model="state.cv.accent_color" x-bind:value="color">
                        <span class="size-4 rounded-sm border border-neutral-400" x-bind:style="`background-color: ${color}`" aria-hidden="true"></span>
                        <span class="sr-only" x-text="color"></span>
                    </label>
                </template>
            </div>
            <p class="mt-1 text-sm text-red-800" x-show="errors.accent_color" x-text="errors.accent_color" role="alert"></p>
        </fieldset>

        <div class="border-t border-line pt-4">
            <p class="text-sm text-muted" aria-live="polite" role="status" x-text="pageCountMessage"></p>
            <button class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="button" x-on:click="downloadPdf()" x-bind:disabled="busy" x-text="paymentPaid ? messages.downloadPdf : messages.paymentDownload"><?= esc(lang('Payments.download_action')) ?></button>
        </div>
    </div>

    <div class="min-w-0">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-accent-900"><?= esc(lang('Themes.current_theme')) ?></p>
                <h3 class="mt-1 text-lg font-semibold text-ink"><?= esc(lang('CvBuilder.pdf_preview_title')) ?></h3>
            </div>
            <p class="max-w-xs text-right text-xs leading-5 text-muted"><?= esc(lang('CvBuilder.preview_changes_note')) ?></p>
        </div>
        <div class="overflow-hidden rounded-md border border-neutral-300 bg-neutral-100 p-2 sm:p-4">
            <iframe
                class="mx-auto h-[72vh] min-h-[32rem] w-full max-w-[52rem] border border-neutral-300 bg-white"
                title="<?= esc(lang('CvBuilder.pdf_preview_title')) ?>"
                loading="lazy"
                x-bind:src="previewFrameUrl"
            ></iframe>
        </div>
    </div>
</div>
