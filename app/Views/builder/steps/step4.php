<h2 class="mb-5 text-xl font-semibold"><?= esc(lang('CvBuilder.educations')) ?></h2>
<template x-for="(row, index) in state.educations" x-bind:key="index">
    <fieldset class="mb-4 grid gap-3 rounded-md border border-neutral-200 p-4 sm:grid-cols-2">
        <legend class="px-1 text-sm font-medium"><?= esc(lang('CvBuilder.educations')) ?> <span x-text="index + 1"></span></legend>
        <?php foreach (['institution', 'degree', 'field_of_study', 'gpa'] as $field): ?>
            <div>
                <label class="mb-1 block text-sm font-medium" x-bind:for="'education-' + index + '-<?= esc($field) ?>"><?= esc(lang('CvBuilder.' . $field)) ?></label>
                <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'education-' + index + '-<?= esc($field) ?>'" x-model="row.<?= esc($field) ?>" maxlength="<?= ['institution' => 200, 'degree' => 120, 'field_of_study' => 160, 'gpa' => 20][$field] ?>">
                <p class="mt-1 text-sm text-red-800" x-show="errors['educations.' + index + '.<?= esc($field) ?>']" x-text="errors['educations.' + index + '.<?= esc($field) ?>']" role="alert"></p>
            </div>
        <?php endforeach; ?>
        <?php foreach (['start_date', 'end_date'] as $field): ?>
            <div>
                <label class="mb-1 block text-sm font-medium" x-bind:for="'education-' + index + '-<?= esc($field) ?>"><?= esc(lang('CvBuilder.' . $field)) ?></label>
                <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'education-' + index + '-<?= esc($field) ?>'" x-model="row.<?= esc($field) ?>" type="date">
                <p class="mt-1 text-sm text-red-800" x-show="errors['educations.' + index + '.<?= esc($field) ?>']" x-text="errors['educations.' + index + '.<?= esc($field) ?>']" role="alert"></p>
            </div>
        <?php endforeach; ?>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-sm font-medium" x-bind:for="'education-' + index + '-description'"><?= esc(lang('CvBuilder.description_label')) ?></label>
            <textarea class="min-h-24 w-full rounded-md border border-neutral-400 p-3" x-bind:id="'education-' + index + '-description'" x-model="row.description" maxlength="3000"></textarea>
            <p class="mt-1 text-sm text-red-800" x-show="errors['educations.' + index + '.description']" x-text="errors['educations.' + index + '.description']" role="alert"></p>
        </div>
        <div class="flex gap-2 sm:col-span-2">
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.educations, index, -1)" x-bind:disabled="index === 0"><?= esc(lang('CvBuilder.move_up')) ?></button>
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.educations, index, 1)" x-bind:disabled="index === state.educations.length - 1"><?= esc(lang('CvBuilder.move_down')) ?></button>
            <button class="min-h-9 rounded-md border border-red-700 px-3 text-sm text-red-800" type="button" x-on:click="remove(state.educations, index)"><?= esc(lang('CvBuilder.remove_item')) ?></button>
        </div>
    </fieldset>
</template>
<p class="mb-2 text-sm text-red-800" x-show="errors.educations" x-text="errors.educations" role="alert"></p>
<button class="min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium" type="button" x-on:click="add(state.educations, { institution: '', degree: '', field_of_study: '', gpa: '', start_date: '', end_date: '', description: '' })"><?= esc(lang('CvBuilder.add_item')) ?></button>
