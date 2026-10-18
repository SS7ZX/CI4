<h2 class="mb-2 text-xl font-semibold"><?= esc(lang('CvBuilder.steps')[7]) ?></h2>
<p class="mb-5 text-sm text-neutral-700"><?= esc(lang('CvBuilder.pdf_review_help')) ?></p>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="paper-size" class="mb-1 block text-sm font-medium"><?= esc(lang('CvBuilder.paper_size')) ?></label>
        <select id="paper-size" class="min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" x-model="state.cv.paper_size">
            <option value="a4"><?= esc(lang('CvBuilder.paper_a4')) ?></option>
            <option value="letter"><?= esc(lang('CvBuilder.paper_letter')) ?></option>
        </select>
        <p class="mt-1 text-sm text-red-800" x-show="errors.paper_size" x-text="errors.paper_size" role="alert"></p>
    </div>
    <div>
        <label for="font-scale" class="mb-1 block text-sm font-medium"><?= esc(lang('CvBuilder.font_scale')) ?></label>
        <select id="font-scale" class="min-h-11 w-full rounded-md border border-neutral-400 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" x-model="state.cv.font_scale">
            <option value="small"><?= esc(lang('CvBuilder.font_small')) ?></option>
            <option value="medium"><?= esc(lang('CvBuilder.font_medium')) ?></option>
            <option value="large"><?= esc(lang('CvBuilder.font_large')) ?></option>
        </select>
        <p class="mt-1 text-sm text-red-800" x-show="errors.font_scale" x-text="errors.font_scale" role="alert"></p>
    </div>
</div>

<fieldset class="mt-5">
    <legend class="mb-2 text-sm font-medium"><?= esc(lang('CvBuilder.accent_color')) ?></legend>
    <div class="flex flex-wrap gap-3">
        <template x-for="color in selectedPalette()" x-bind:key="color">
            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-neutral-300 px-3 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent-700">
                <input class="size-4 accent-accent-700" type="radio" name="accent_color" x-model="state.cv.accent_color" x-bind:value="color">
                <span class="size-5 rounded-sm border border-neutral-400" x-bind:style="`background-color: ${color}`" aria-hidden="true"></span>
                <span class="text-sm" x-text="color"></span>
            </label>
        </template>
    </div>
    <p class="mt-1 text-sm text-red-800" x-show="errors.accent_color" x-text="errors.accent_color" role="alert"></p>
</fieldset>

<div class="mt-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-neutral-700" aria-live="polite" role="status" x-text="pageCountMessage"></p>
    <button class="inline-flex min-h-11 items-center rounded-md bg-accent-700 px-4 text-sm font-medium text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-wait disabled:opacity-60" type="button" x-on:click="downloadPdf()" x-bind:disabled="busy"><?= esc(lang('CvBuilder.download_pdf')) ?></button>
</div>

<h3 class="mb-2 mt-6 text-lg font-semibold"><?= esc(lang('CvBuilder.pdf_preview_title')) ?></h3>
<iframe class="h-[70vh] min-h-96 w-full border border-neutral-300 bg-white" title="<?= esc(lang('CvBuilder.pdf_preview_title')) ?>" x-bind:src="previewFrameUrl"></iframe>
