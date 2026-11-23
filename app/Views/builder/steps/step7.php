<h2 class="mb-2 text-xl font-semibold tracking-tight text-ink"><?= esc(lang('Themes.step_title')) ?></h2>
<p class="mb-5 text-sm leading-6 text-muted"><?= esc(lang('CvBuilder.step_7_description')) ?></p>

<div x-data="{ themeQuery: '', atsFilter: 'all' }">
<div class="mb-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
    <div>
        <label class="mb-1 block text-sm font-medium" for="theme-search"><?= esc(lang('Themes.search_label')) ?></label>
        <input id="theme-search" type="search" x-model="themeQuery" class="min-h-11 w-full rounded-md border border-line bg-paper px-3" placeholder="<?= esc(lang('Themes.search_placeholder')) ?>">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium" for="theme-filter"><?= esc(lang('Themes.filter_label')) ?></label>
        <select id="theme-filter" x-model="atsFilter" class="min-h-11 w-full rounded-md border border-line bg-paper px-3 sm:w-52">
            <option value="all"><?= esc(lang('Themes.filter_all')) ?></option>
            <option value="ats"><?= esc(lang('Themes.ats_badge')) ?></option>
            <option value="visual"><?= esc(lang('Themes.non_ats_badge')) ?></option>
        </select>
    </div>
</div>

<fieldset class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 reveal-stagger">
    <legend class="sr-only"><?= esc(lang('Themes.step_title')) ?></legend>
    <?php foreach ($themes as $theme): ?>
        <?php $themeInputId = 'theme-' . $theme['key']; ?>
        <div
            class="theme-card overflow-hidden rounded-md border border-line hover:bg-canvas focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent-700"
            x-bind:class="selectedTheme === <?= esc(json_encode($theme['key'], JSON_THROW_ON_ERROR), 'attr') ?> ? 'border-accent-700 bg-accent-50' : ''"
            x-show="(themeQuery === '' || <?= esc(json_encode(mb_strtolower(lang($theme['name_key']) . ' ' . lang($theme['description_key']))), 'attr') ?>.includes(themeQuery.toLowerCase())) && (atsFilter === 'all' || (atsFilter === 'ats' && <?= $theme['ats_friendly'] ? 'true' : 'false' ?>) || (atsFilter === 'visual' && <?= $theme['ats_friendly'] ? 'false' : 'true' ?>))"
        >
            <?= view('themes/thumbnail', ['theme' => $theme, 'featured' => false, 'compact' => false]) ?>
            <div class="p-4">
            <span class="flex items-start gap-3">
                <input class="mt-1 size-4 shrink-0 accent-accent-700" id="<?= esc($themeInputId, 'attr') ?>" type="radio" name="theme_key" value="<?= esc($theme['key'], 'attr') ?>" x-model="selectedTheme">
                <span class="min-w-0 flex-1">
                    <label class="block cursor-pointer" for="<?= esc($themeInputId, 'attr') ?>">
                        <span class="block font-semibold"><?= esc(lang($theme['name_key'])) ?></span>
                        <span class="mt-1 block text-sm text-muted"><?= esc(lang($theme['description_key'])) ?></span>
                        <?php if ($theme['ats_friendly']): ?>
                            <span class="mt-3 inline-flex rounded-md border border-green-700 px-2 py-1 text-xs font-semibold text-green-900"><?= esc(lang('Themes.ats_badge')) ?></span>
                        <?php else: ?>
                            <span class="mt-3 inline-flex rounded-md border border-amber-700 px-2 py-1 text-xs font-semibold text-amber-900"><?= esc(lang('Themes.non_ats_badge')) ?></span>
                            <span class="mt-2 block text-xs text-amber-900"><?= esc(lang('Themes.ats_visual_warning')) ?></span>
                        <?php endif; ?>
                    </label>
                    <span class="mt-3 block text-sm font-medium text-accent-700" x-show="selectedTheme === <?= esc(json_encode($theme['key'], JSON_THROW_ON_ERROR), 'attr') ?>"><?= esc(lang('Themes.selected')) ?></span>
                    <button class="mt-3 min-h-10 rounded-md border border-line px-3 text-sm font-medium hover:bg-paper focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="button" x-on:click.stop="previewTheme(<?= esc(json_encode($theme['key'], JSON_THROW_ON_ERROR), 'attr') ?>)"><?= esc(lang('Themes.preview')) ?></button>
                </span>
            </span>
            </div>
        </div>
    <?php endforeach; ?>
</fieldset>
</div>
<p class="mt-4 text-sm leading-6 text-muted"><?= esc(lang('Themes.ats_scope_note')) ?></p>
<p class="mt-3 text-sm text-red-800" x-show="errors.theme_key" x-text="errors.theme_key" role="alert"></p>

<div
    class="theme-drawer-backdrop fixed inset-0 z-50"
    x-cloak
    x-show="previewOpen"
    x-transition:enter="motion-drawer-enter-active"
    x-transition:enter-start="motion-drawer-enter-start"
    x-transition:enter-end="motion-drawer-enter-end"
    x-transition:leave="motion-drawer-leave-active"
    x-transition:leave-start="motion-drawer-leave-start"
    x-transition:leave-end="motion-drawer-leave-end"
    x-on:keydown.escape.window="closePreview()"
    x-on:keydown.tab="trapPreviewFocus($event)"
    role="dialog"
    aria-modal="true"
    aria-label="<?= esc(lang('Themes.preview_title')) ?>"
    x-ref="previewDialog"
>
    <div class="theme-drawer-panel flex flex-col border-l border-line bg-paper p-3 sm:p-5">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold"><?= esc(lang('Themes.preview_title')) ?></h3>
                <p class="mt-1 text-sm font-medium text-ink" x-text="previewThemeName()"></p>
                <p class="mt-1 text-xs text-muted" x-cloak x-show="previewThemeIsAtsFriendly()"><?= esc(lang('Themes.ats_badge')) ?></p>
                <p class="mt-1 text-xs text-muted" x-cloak x-show="!previewThemeIsAtsFriendly()"><?= esc(lang('Themes.non_ats_badge')) ?></p>
            </div>
            <button class="min-h-10 rounded-md border border-line px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="button" x-on:click="closePreview()" x-ref="previewClose"><?= esc(lang('Themes.close_preview')) ?></button>
        </div>
        <template x-if="previewOpen">
            <iframe class="preview-iframe min-h-0 w-full flex-1 border border-line" title="<?= esc(lang('Themes.preview_frame_title')) ?>" x-bind:class="previewUpdating ? 'is-loading' : ''" x-bind:src="previewFrameUrl" x-on:load="configurePreviewFrame($event)" sandbox="allow-same-origin"></iframe>
        </template>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-2xl text-xs leading-5 text-muted"><?= esc(lang('Themes.ats_scope_note')) ?></p>
            <button class="inline-flex min-h-11 items-center justify-center rounded-md bg-accent-700 px-4 text-sm font-semibold text-white hover:bg-accent-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-900 disabled:cursor-not-allowed disabled:opacity-60" type="button" x-on:click="applyPreviewTheme()" x-bind:disabled="selectedTheme === previewThemeKey">
                <span x-cloak x-show="selectedTheme !== previewThemeKey"><?= esc(lang('Themes.apply_preview')) ?></span>
                <span x-cloak x-show="selectedTheme === previewThemeKey"><?= esc(lang('Themes.selected')) ?></span>
            </button>
        </div>
    </div>
</div>
