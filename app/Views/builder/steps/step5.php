<h2 class="mb-5 text-xl font-semibold"><?= esc(lang('CvBuilder.skills')) ?></h2>
<template x-for="(row, index) in state.skill_groups" x-bind:key="index">
    <fieldset class="mb-4 grid gap-3 rounded-md border border-neutral-200 p-4 sm:grid-cols-2">
        <legend class="px-1 text-sm font-medium"><?= esc(lang('CvBuilder.skills')) ?> <span x-text="index + 1"></span></legend>
        <div>
            <label class="mb-1 block text-sm font-medium" x-bind:for="'skill-name-' + index"><?= esc(lang('CvBuilder.skill_name')) ?></label>
            <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'skill-name-' + index" x-model="row.name" maxlength="120">
            <p class="mt-1 text-sm text-red-800" x-show="errors['skill_groups.' + index + '.name']" x-text="errors['skill_groups.' + index + '.name']" role="alert"></p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium" x-bind:for="'skill-items-' + index"><?= esc(lang('CvBuilder.skill_items')) ?></label>
            <textarea class="min-h-24 w-full rounded-md border border-neutral-400 p-3" x-bind:id="'skill-items-' + index" x-model="row.items_text" maxlength="3000"></textarea>
            <p class="mt-1 text-sm text-red-800" x-show="errors['skill_groups.' + index + '.items']" x-text="errors['skill_groups.' + index + '.items']" role="alert"></p>
        </div>
        <div class="flex gap-2 sm:col-span-2">
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.skill_groups, index, -1)" x-bind:disabled="index === 0"><?= esc(lang('CvBuilder.move_up')) ?></button>
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.skill_groups, index, 1)" x-bind:disabled="index === state.skill_groups.length - 1"><?= esc(lang('CvBuilder.move_down')) ?></button>
            <button class="min-h-9 rounded-md border border-red-700 px-3 text-sm text-red-800" type="button" x-on:click="remove(state.skill_groups, index)"><?= esc(lang('CvBuilder.remove_item')) ?></button>
        </div>
    </fieldset>
</template>
<p class="mb-2 text-sm text-red-800" x-show="errors.skill_groups" x-text="errors.skill_groups" role="alert"></p>
<button class="min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium" type="button" x-on:click="add(state.skill_groups, { name: '', items: [], items_text: '' })"><?= esc(lang('CvBuilder.add_item')) ?></button>
