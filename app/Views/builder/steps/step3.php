<h2 class="mb-5 text-xl font-semibold"><?= esc(lang('CvBuilder.experiences')) ?></h2>
<template x-for="(row, index) in state.experiences" x-bind:key="index">
    <fieldset class="mb-4 grid gap-3 rounded-md border border-neutral-200 p-4 sm:grid-cols-2">
        <legend class="px-1 text-sm font-medium"><?= esc(lang('CvBuilder.experiences')) ?> <span x-text="index + 1"></span></legend>
        <?php foreach (['company' => 'company', 'position' => 'position', 'location' => 'location'] as $field => $label): ?>
            <div>
                <label class="mb-1 block text-sm font-medium" x-bind:for="'experience-' + index + '-<?= esc($field) ?>"><?= esc(lang('CvBuilder.' . $label)) ?></label>
                <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'experience-' + index + '-<?= esc($field) ?>'" x-model="row.<?= esc($field) ?>" maxlength="<?= $field === 'company' || $field === 'position' ? '160' : '160' ?>">
                <p class="mt-1 text-sm text-red-800" x-show="errors['experiences.' + index + '.<?= esc($field) ?>']" x-text="errors['experiences.' + index + '.<?= esc($field) ?>']" role="alert"></p>
            </div>
        <?php endforeach; ?>
        <?php foreach (['start_date', 'end_date'] as $field): ?>
            <div x-bind:class="<?= $field === 'end_date' ? 'row.is_current ? \'hidden\' : \'\'' : '\'\' ' ?>">
                <label class="mb-1 block text-sm font-medium" x-bind:for="'experience-' + index + '-<?= esc($field) ?>"><?= esc(lang('CvBuilder.' . $field)) ?></label>
                <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'experience-' + index + '-<?= esc($field) ?>'" x-model="row.<?= esc($field) ?>" type="date">
                <p class="mt-1 text-sm text-red-800" x-show="errors['experiences.' + index + '.<?= esc($field) ?>']" x-text="errors['experiences.' + index + '.<?= esc($field) ?>']" role="alert"></p>
            </div>
        <?php endforeach; ?>
        <label class="flex items-center gap-2 text-sm sm:col-span-2">
            <input class="size-4 accent-accent-700" type="checkbox" x-model="row.is_current" x-on:change="if (row.is_current) row.end_date = null">
            <?= esc(lang('CvBuilder.currently_working')) ?>
        </label>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-sm font-medium" x-bind:for="'experience-' + index + '-bullets'"><?= esc(lang('CvBuilder.bullets')) ?></label>
            <textarea class="min-h-28 w-full rounded-md border border-neutral-400 p-3" x-bind:id="'experience-' + index + '-bullets'" x-model="row.bullets_text" maxlength="6000"></textarea>
            <p class="mt-1 text-sm text-red-800" x-show="errors['experiences.' + index + '.bullets']" x-text="errors['experiences.' + index + '.bullets']" role="alert"></p>
        </div>
        <div class="flex gap-2 sm:col-span-2">
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.experiences, index, -1)" x-bind:disabled="index === 0"><?= esc(lang('CvBuilder.move_up')) ?></button>
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.experiences, index, 1)" x-bind:disabled="index === state.experiences.length - 1"><?= esc(lang('CvBuilder.move_down')) ?></button>
            <button class="min-h-9 rounded-md border border-red-700 px-3 text-sm text-red-800" type="button" x-on:click="remove(state.experiences, index)"><?= esc(lang('CvBuilder.remove_item')) ?></button>
        </div>
    </fieldset>
</template>
<p class="mb-2 text-sm text-red-800" x-show="errors.experiences" x-text="errors.experiences" role="alert"></p>
<button class="min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium" type="button" x-on:click="add(state.experiences, { company: '', position: '', location: '', start_date: '', end_date: '', is_current: false, bullets: [], bullets_text: '' })"><?= esc(lang('CvBuilder.add_item')) ?></button>
