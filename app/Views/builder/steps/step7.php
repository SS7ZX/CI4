<h2 class="mb-2 text-xl font-semibold"><?= esc(lang('Themes.step_title')) ?></h2>
<p class="mb-5 text-sm text-neutral-700"><?= esc(lang('Themes.step_description')) ?></p>

<fieldset class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <legend class="sr-only"><?= esc(lang('Themes.step_title')) ?></legend>
    <?php foreach ($themes as $theme): ?>
        <?php $themeInputId = 'theme-' . $theme['key']; ?>
        <div class="rounded-md border border-neutral-300 p-4 hover:bg-neutral-50 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent-700" x-bind:class="selectedTheme === <?= esc(json_encode($theme['key'], JSON_THROW_ON_ERROR), 'attr') ?> ? 'border-accent-700 bg-accent-50' : ''">
            <span class="flex items-start gap-3">
                <input class="mt-1 size-4 shrink-0 accent-accent-700" id="<?= esc($themeInputId, 'attr') ?>" type="radio" name="theme_key" value="<?= esc($theme['key'], 'attr') ?>" x-model="selectedTheme">
                <span class="min-w-0 flex-1">
                    <label class="block cursor-pointer" for="<?= esc($themeInputId, 'attr') ?>">
                        <span class="block font-semibold"><?= esc(lang($theme['name_key'])) ?></span>
                        <span class="mt-1 block text-sm text-neutral-700"><?= esc(lang($theme['description_key'])) ?></span>
                        <?php if ($theme['ats_friendly']): ?>
                            <span class="mt-3 inline-flex rounded-md border border-green-700 px-2 py-1 text-xs font-semibold text-green-900"><?= esc(lang('Themes.ats_badge')) ?></span>
                        <?php else: ?>
                            <span class="mt-3 inline-flex rounded-md border border-amber-700 px-2 py-1 text-xs font-semibold text-amber-900"><?= esc(lang('Themes.non_ats_badge')) ?></span>
                            <span class="mt-2 block text-xs text-amber-900"><?= esc(lang('Themes.ats_warning')) ?></span>
                        <?php endif; ?>
                    </label>
                    <span class="mt-3 block text-sm font-medium text-accent-700" x-show="selectedTheme === <?= esc(json_encode($theme['key'], JSON_THROW_ON_ERROR), 'attr') ?>"><?= esc(lang('Themes.selected')) ?></span>
                    <button class="mt-3 min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="button" x-on:click.stop="previewTheme(<?= esc(json_encode($theme['key'], JSON_THROW_ON_ERROR), 'attr') ?>)"><?= esc(lang('Themes.preview')) ?></button>
                </span>
            </span>
        </div>
    <?php endforeach; ?>
</fieldset>
<p class="mt-3 text-sm text-red-800" x-show="errors.theme_key" x-text="errors.theme_key" role="alert"></p>

<div
    class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900 p-3 sm:p-6"
    x-cloak
    x-show="previewOpen"
    x-on:keydown.escape.window="closePreview()"
    x-on:keydown.tab="trapPreviewFocus($event)"
    role="dialog"
    aria-modal="true"
    aria-label="<?= esc(lang('Themes.preview_title')) ?>"
    x-ref="previewDialog"
>
    <div class="flex h-full max-h-[92vh] w-full max-w-6xl flex-col rounded-md border border-neutral-300 bg-white p-3 sm:p-5">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold"><?= esc(lang('Themes.preview_title')) ?></h3>
            <button class="min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="button" x-on:click="closePreview()" x-ref="previewClose"><?= esc(lang('Themes.close_preview')) ?></button>
        </div>
        <template x-if="previewOpen">
            <iframe class="min-h-0 w-full flex-1 border border-neutral-200" title="<?= esc(lang('Themes.preview_frame_title')) ?>" x-bind:src="previewFrameUrl" x-on:load="configurePreviewFrame($event)" sandbox="allow-same-origin"></iframe>
        </template>
    </div>
</div>
