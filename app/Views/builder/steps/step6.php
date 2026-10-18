<h2 class="mb-5 text-xl font-semibold"><?= esc(lang('CvBuilder.additional')) ?></h2>

<?php
$additionalCollections = [
    'projects' => [
        'title' => 'projects',
        'fields' => [
            'name'       => ['label' => 'project_name', 'type' => 'text', 'max' => 160],
            'role'       => ['label' => 'role', 'type' => 'text', 'max' => 120],
            'url'        => ['label' => 'url', 'type' => 'url', 'max' => 2048],
            'start_date' => ['label' => 'start_date', 'type' => 'date'],
            'end_date'   => ['label' => 'end_date', 'type' => 'date'],
            'bullets'    => ['label' => 'bullets', 'type' => 'lines', 'max' => 3000],
            'tech_stack' => ['label' => 'tech_stack', 'type' => 'lines', 'max' => 1000],
        ],
        'empty' => ['name' => '', 'role' => '', 'url' => '', 'start_date' => '', 'end_date' => '', 'bullets' => [], 'bullets_text' => '', 'tech_stack' => [], 'tech_stack_text' => ''],
    ],
    'certifications' => [
        'title' => 'certifications',
        'fields' => [
            'name'           => ['label' => 'certification_name', 'type' => 'text', 'max' => 180],
            'issuer'         => ['label' => 'issuer', 'type' => 'text', 'max' => 180],
            'issued_at'      => ['label' => 'issued_at', 'type' => 'date'],
            'expires_at'     => ['label' => 'expires_at', 'type' => 'date'],
            'credential_url' => ['label' => 'credential_url', 'type' => 'url', 'max' => 2048],
        ],
        'empty' => ['name' => '', 'issuer' => '', 'issued_at' => '', 'expires_at' => '', 'credential_url' => ''],
    ],
    'organizations' => [
        'title' => 'organizations',
        'fields' => [
            'organization' => ['label' => 'organization', 'type' => 'text', 'max' => 180],
            'role'         => ['label' => 'role', 'type' => 'text', 'max' => 120],
            'start_date'   => ['label' => 'start_date', 'type' => 'date'],
            'end_date'     => ['label' => 'end_date', 'type' => 'date'],
            'bullets'      => ['label' => 'bullets', 'type' => 'lines', 'max' => 3000],
        ],
        'empty' => ['organization' => '', 'role' => '', 'start_date' => '', 'end_date' => '', 'bullets' => [], 'bullets_text' => ''],
    ],
    'awards' => [
        'title' => 'awards',
        'fields' => [
            'title'       => ['label' => 'award_title', 'type' => 'text', 'max' => 180],
            'issuer'      => ['label' => 'issuer', 'type' => 'text', 'max' => 180],
            'awarded_at'  => ['label' => 'awarded_at', 'type' => 'date'],
            'description' => ['label' => 'description_label', 'type' => 'textarea', 'max' => 3000],
        ],
        'empty' => ['title' => '', 'issuer' => '', 'awarded_at' => '', 'description' => ''],
    ],
    'languages' => [
        'title' => 'languages',
        'fields' => [
            'name'  => ['label' => 'language_name', 'type' => 'text', 'max' => 120],
            'level' => ['label' => 'language_level', 'type' => 'level'],
        ],
        'empty' => ['name' => '', 'level' => 'professional'],
    ],
    'custom_sections' => [
        'title' => 'custom_sections',
        'fields' => [
            'title' => ['label' => 'section_title', 'type' => 'text', 'max' => 120],
            'items' => ['label' => 'section_items', 'type' => 'lines', 'max' => 1000],
        ],
        'empty' => ['title' => '', 'items' => [], 'items_text' => ''],
    ],
];
?>

<?php foreach ($additionalCollections as $collection => $definition): ?>
    <section class="mb-7 border-b border-neutral-200 pb-6">
        <h3 class="mb-3 text-lg font-semibold"><?= esc(lang('CvBuilder.' . $definition['title'])) ?></h3>
        <template x-for="(row, index) in state.<?= esc($collection) ?>" x-bind:key="index">
            <fieldset class="mb-4 grid gap-3 rounded-md border border-neutral-200 p-4 sm:grid-cols-2">
                <legend class="px-1 text-sm font-medium"><?= esc(lang('CvBuilder.' . $definition['title'])) ?> <span x-text="index + 1"></span></legend>
                <?php foreach ($definition['fields'] as $field => $fieldDefinition): ?>
                    <?php $isLines = $fieldDefinition['type'] === 'lines'; ?>
                    <div class="<?= $isLines || $fieldDefinition['type'] === 'textarea' ? 'sm:col-span-2' : '' ?>">
                        <label class="mb-1 block text-sm font-medium" x-bind:for="'<?= esc($collection) ?>-' + index + '-<?= esc($field) ?>"><?= esc(lang('CvBuilder.' . $fieldDefinition['label'])) ?></label>
                        <?php if ($fieldDefinition['type'] === 'level'): ?>
                            <select class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'<?= esc($collection) ?>-' + index + '-<?= esc($field) ?>'" x-model="row.level">
                                <?php foreach (['basic', 'conversational', 'professional', 'native'] as $level): ?>
                                    <option value="<?= esc($level) ?>"><?= esc(lang('CvBuilder.' . $level)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($isLines || $fieldDefinition['type'] === 'textarea'): ?>
                            <?php $modelField = $isLines ? $field . '_text' : $field; ?>
                            <textarea class="min-h-24 w-full rounded-md border border-neutral-400 p-3" x-bind:id="'<?= esc($collection) ?>-' + index + '-<?= esc($field) ?>'" x-model="row.<?= esc($modelField) ?>" maxlength="<?= (int) $fieldDefinition['max'] ?>"></textarea>
                        <?php else: ?>
                            <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'<?= esc($collection) ?>-' + index + '-<?= esc($field) ?>'" x-model="row.<?= esc($field) ?>" type="<?= esc($fieldDefinition['type']) ?>" <?= isset($fieldDefinition['max']) ? 'maxlength="' . (int) $fieldDefinition['max'] . '"' : '' ?>>
                        <?php endif; ?>
                        <p class="mt-1 text-sm text-red-800" x-show="errors['<?= esc($collection) ?>.' + index + '.<?= esc($field) ?>']" x-text="errors['<?= esc($collection) ?>.' + index + '.<?= esc($field) ?>']" role="alert"></p>
                    </div>
                <?php endforeach; ?>
                <div class="flex gap-2 sm:col-span-2">
                    <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.<?= esc($collection) ?>, index, -1)" x-bind:disabled="index === 0"><?= esc(lang('CvBuilder.move_up')) ?></button>
                    <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.<?= esc($collection) ?>, index, 1)" x-bind:disabled="index === state.<?= esc($collection) ?>.length - 1"><?= esc(lang('CvBuilder.move_down')) ?></button>
                    <button class="min-h-9 rounded-md border border-red-700 px-3 text-sm text-red-800" type="button" x-on:click="remove(state.<?= esc($collection) ?>, index)"><?= esc(lang('CvBuilder.remove_item')) ?></button>
                </div>
            </fieldset>
        </template>
        <p class="mb-2 text-sm text-red-800" x-show="errors.<?= esc($collection) ?>" x-text="errors.<?= esc($collection) ?>" role="alert"></p>
        <button class="min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium" type="button" x-on:click="add(state.<?= esc($collection) ?>, <?= esc(json_encode($definition['empty'], JSON_THROW_ON_ERROR), 'attr') ?>)"><?= esc(lang('CvBuilder.add_item')) ?></button>
    </section>
<?php endforeach; ?>

<section>
    <h3 class="mb-3 text-lg font-semibold"><?= esc(lang('CvBuilder.section_settings')) ?></h3>
    <p class="mb-3 text-sm text-neutral-700"><?= esc(lang('CvBuilder.section_settings_help')) ?></p>
    <template x-for="(section, index) in state.sections" x-bind:key="section.section_key">
        <div class="mb-2 flex flex-wrap items-center gap-3 rounded-md border border-neutral-200 p-3">
            <label class="flex min-h-10 flex-1 items-center gap-2 text-sm font-medium">
                <input class="size-4 accent-accent-700" type="checkbox" x-model="section.is_visible">
                <span x-text="sectionLabels[section.section_key] ?? section.section_key"></span>
            </label>
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.sections, index, -1)" x-bind:disabled="index === 0" aria-label="<?= esc(lang('CvBuilder.move_up')) ?>"><?= esc(lang('CvBuilder.move_up')) ?></button>
            <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.sections, index, 1)" x-bind:disabled="index === state.sections.length - 1" aria-label="<?= esc(lang('CvBuilder.move_down')) ?>"><?= esc(lang('CvBuilder.move_down')) ?></button>
        </div>
    </template>
</section>
