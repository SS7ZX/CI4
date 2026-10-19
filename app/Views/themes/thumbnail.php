<?php $modifier = ! empty($featured) ? 'theme-preview--featured' : (! empty($compact) ? 'theme-preview--compact' : ''); ?>
<div class="theme-preview<?= $modifier !== '' ? ' ' . esc($modifier, 'attr') : '' ?>" style="--theme-preview-accent: <?= esc($theme['accent_palette'][0], 'attr') ?>" aria-hidden="true">
    <div class="theme-preview__page" data-layout="<?= esc($theme['layout'], 'attr') ?>" data-theme="<?= esc($theme['key'], 'attr') ?>">
        <div class="theme-preview__header">
            <span class="theme-preview__line theme-preview__line--title"></span>
            <span class="theme-preview__line theme-preview__line--short"></span>
            <span class="theme-preview__line theme-preview__line--contact"></span>
        </div>
        <div class="theme-preview__body">
            <aside class="theme-preview__sidebar">
                <span class="theme-preview__label"><?= esc(lang('Themes.preview_profile')) ?></span>
                <span class="theme-preview__line"></span>
                <span class="theme-preview__line theme-preview__line--short"></span>
                <span class="theme-preview__label"><?= esc(lang('Themes.preview_contact')) ?></span>
                <span class="theme-preview__line"></span>
                <span class="theme-preview__line theme-preview__line--short"></span>
            </aside>
            <div class="theme-preview__main">
                <?php foreach (['preview_experience', 'preview_education', 'preview_skills'] as $section): ?>
                    <section class="theme-preview__section">
                        <span class="theme-preview__label"><?= esc(lang('Themes.' . $section)) ?></span>
                        <?php for ($entry = 0; $entry < 2; $entry++): ?>
                            <div class="theme-preview__entry">
                                <span class="theme-preview__line"></span>
                                <span class="theme-preview__line theme-preview__line--short"></span>
                            </div>
                        <?php endfor; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
