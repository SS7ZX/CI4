<h2 class="mb-5 text-xl font-semibold"><?= esc(lang('CvBuilder.steps')[0]) ?></h2>
<div class="grid gap-4 sm:grid-cols-2">
    <?php foreach ([
        'title' => ['label' => lang('Dashboard.new_cv_title'), 'required' => true],
        'full_name' => ['label' => lang('CvBuilder.full_name'), 'required' => true],
        'headline' => ['label' => lang('CvBuilder.headline'), 'required' => false],
        'email' => ['label' => lang('CvBuilder.email'), 'required' => false],
        'phone' => ['label' => lang('CvBuilder.phone'), 'required' => false],
        'city' => ['label' => lang('CvBuilder.city'), 'required' => false],
        'country' => ['label' => lang('CvBuilder.country'), 'required' => false],
    ] as $field => $item): ?>
        <div>
            <label class="mb-1 block text-sm font-medium" for="profile-<?= esc($field) ?>"><?= esc($item['label']) ?></label>
            <input
                class="min-h-11 w-full rounded-md border border-neutral-400 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700"
                id="profile-<?= esc($field) ?>"
                type="<?= $field === 'email' ? 'email' : 'text' ?>"
                maxlength="<?= $field === 'title' ? '160' : ($field === 'full_name' ? '160' : ($field === 'headline' ? '160' : ($field === 'phone' ? '40' : ($field === 'email' ? '254' : '120')))) ?>"
                x-model="state.<?= $field === 'title' ? 'cv.title' : 'profile.' . $field ?>"
                <?= $item['required'] ? 'required' : '' ?>
                x-bind:aria-invalid="<?= $field === 'title' ? "errors['title']" : "errors['profile." . esc($field) . "']" ?> ? 'true' : 'false'"
            >
            <p class="mt-1 text-sm text-red-800" x-show="<?= $field === 'title' ? "errors['title']" : "errors['profile." . esc($field) . "']" ?>" x-text="<?= $field === 'title' ? "errors['title']" : "errors['profile." . esc($field) . "']" ?>" role="alert"></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-6 border-t border-neutral-200 pt-5">
    <h3 class="mb-2 text-lg font-semibold"><?= esc(lang('CvBuilder.photo')) ?></h3>
    <template x-if="state.profile.photo_path">
        <img class="mb-3 size-24 rounded-md border border-neutral-200 object-cover" src="<?= esc(url_to('cv-photo', $cvId)) ?>" alt="<?= esc(lang('CvBuilder.photo')) ?>">
    </template>
    <form action="<?= esc(url_to('cv-photo-upload', $cvId)) ?>" method="post" enctype="multipart/form-data" x-on:submit.prevent="uploadPhoto($event)" class="flex flex-wrap items-end gap-3">
        <?= csrf_field() ?>
        <div>
            <label class="mb-1 block text-sm font-medium" for="profile-photo"><?= esc(lang('CvBuilder.photo')) ?></label>
            <input class="block min-h-11 max-w-full rounded-md border border-neutral-400 px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" id="profile-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <button class="min-h-11 rounded-md border border-neutral-400 px-4 text-sm font-medium hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-700" type="submit" x-bind:disabled="busy"><?= esc(lang('CvBuilder.upload_photo')) ?></button>
    </form>
    <p class="mt-2 text-sm text-neutral-700"><?= esc(lang('CvBuilder.photo_help')) ?></p>
    <p class="mt-1 text-sm text-green-800" aria-live="polite" x-text="photoMessage"></p>
    <p class="mt-1 text-sm text-red-800" x-show="errors.photo" x-text="errors.photo" role="alert"></p>
</div>

<div class="mt-6 border-t border-neutral-200 pt-5">
    <h3 class="mb-3 text-lg font-semibold"><?= esc(lang('CvBuilder.links')) ?></h3>
    <template x-for="(row, index) in state.links" x-bind:key="index">
        <fieldset class="mb-4 grid gap-3 rounded-md border border-neutral-200 p-4 sm:grid-cols-2">
            <legend class="px-1 text-sm font-medium"><?= esc(lang('CvBuilder.links')) ?> <span x-text="index + 1"></span></legend>
            <div>
                <label class="mb-1 block text-sm font-medium" x-bind:for="'link-label-' + index"><?= esc(lang('CvBuilder.label')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'link-label-' + index" x-model="row.label" maxlength="80">
                <p class="mt-1 text-sm text-red-800" x-show="errors['links.' + index + '.label']" x-text="errors['links.' + index + '.label']" role="alert"></p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" x-bind:for="'link-url-' + index"><?= esc(lang('CvBuilder.url')) ?></label>
                <input class="min-h-11 w-full rounded-md border border-neutral-400 px-3" x-bind:id="'link-url-' + index" x-model="row.url" type="url" maxlength="2048">
                <p class="mt-1 text-sm text-red-800" x-show="errors['links.' + index + '.url']" x-text="errors['links.' + index + '.url']" role="alert"></p>
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.links, index, -1)" x-bind:disabled="index === 0"><?= esc(lang('CvBuilder.move_up')) ?></button>
                <button class="min-h-9 rounded-md border border-neutral-400 px-3 text-sm" type="button" x-on:click="move(state.links, index, 1)" x-bind:disabled="index === state.links.length - 1"><?= esc(lang('CvBuilder.move_down')) ?></button>
                <button class="min-h-9 rounded-md border border-red-700 px-3 text-sm text-red-800" type="button" x-on:click="remove(state.links, index)"><?= esc(lang('CvBuilder.remove_item')) ?></button>
            </div>
        </fieldset>
    </template>
    <p class="mb-2 text-sm text-red-800" x-show="errors.links" x-text="errors.links" role="alert"></p>
    <button class="min-h-10 rounded-md border border-neutral-400 px-3 text-sm font-medium" type="button" x-on:click="add(state.links, { label: '', url: '' })"><?= esc(lang('CvBuilder.add_item')) ?></button>
</div>
