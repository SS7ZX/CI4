<?php

$profileSection = null;
foreach ($document['sections'] as $section) {
    if ($section['key'] === 'profile') {
        $profileSection = $section;
        break;
    }
}
$contactItems = array_filter([
    $document['profile']['email'] ?? null,
    $document['profile']['phone'] ?? null,
    $document['profile']['city'] ?? null,
    $document['profile']['country'] ?? null,
], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
$isSafeUrl = static function (mixed $value): bool {
    if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
};
$renderBullets = static function (mixed $values): void {
    if (! is_array($values) || $values === []) {
        return;
    }
    ?>
    <ul class="cv-list">
        <?php foreach ($values as $value): ?>
            <?php if (is_string($value) && trim($value) !== ''): ?>
                <li><?= esc($value) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<article class="cv-sheet cv-document cv-document--<?= esc($theme['layout'], 'attr') ?>">
    <?php if (($profileSection['visible'] ?? true) && ($document['profile']['full_name'] ?? '') !== ''): ?>
        <header class="cv-header">
            <h1><?= esc($document['profile']['full_name']) ?></h1>
            <?php if (is_string($document['profile']['headline'] ?? null) && $document['profile']['headline'] !== ''): ?>
                <p class="cv-headline"><?= esc($document['profile']['headline']) ?></p>
            <?php endif; ?>
            <?php if ($contactItems !== []): ?>
                <p class="cv-contact"><?= esc(implode(' | ', $contactItems)) ?></p>
            <?php endif; ?>
        </header>
    <?php endif; ?>

    <main class="cv-sections">
        <?php foreach ($document['sections'] as $section): ?>
            <?php
            if (! $section['visible'] || $section['key'] === 'profile') {
                continue;
            }
            $hasContent = is_string($section['content'])
                ? trim($section['content']) !== ''
                : $section['items'] !== [];
            if (! $hasContent) {
                continue;
            }
            ?>
            <section class="cv-section cv-section--<?= esc($section['key'], 'attr') ?>">
                <h2><?= esc($section['label']) ?></h2>
                <?php if ($section['key'] === 'summary'): ?>
                    <p class="cv-summary"><?= nl2br(esc($section['content'])) ?></p>
                <?php elseif ($section['key'] === 'links'): ?>
                    <ul class="cv-list">
                        <?php foreach ($section['items'] as $item): ?>
                            <li>
                                <?php if ($isSafeUrl($item['url'] ?? null)): ?>
                                    <a href="<?= esc($item['url'], 'attr') ?>" rel="noopener noreferrer nofollow"><?= esc($item['label'] ?? '') ?></a>
                                <?php else: ?>
                                    <?= esc($item['label'] ?? '') ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($section['key'] === 'skills'): ?>
                    <?php foreach ($section['items'] as $item): ?>
                        <div class="cv-entry">
                            <h3><?= esc($item['name'] ?? '') ?></h3>
                            <?php $renderBullets($item['items'] ?? []); ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($section['key'] === 'custom_sections'): ?>
                    <?php foreach ($section['items'] as $item): ?>
                        <div class="cv-entry">
                            <h3><?= esc($item['title'] ?? '') ?></h3>
                            <?php $renderBullets($item['items'] ?? []); ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($section['items'] as $item): ?>
                        <?php
                        $title = match ($section['key']) {
                            'experience' => $item['position'] ?? '',
                            'education' => $item['institution'] ?? '',
                            'projects' => $item['name'] ?? '',
                            'certifications' => $item['name'] ?? '',
                            'organizations' => $item['organization'] ?? '',
                            'awards' => $item['title'] ?? '',
                            'languages' => $item['name'] ?? '',
                            default => '',
                        };
                        $subtitle = match ($section['key']) {
                            'experience' => $item['company'] ?? '',
                            'education' => implode(' ', array_filter([
                                $item['degree'] ?? '',
                                $item['field_of_study'] ?? '',
                            ], static fn (mixed $value): bool => is_string($value) && $value !== '')),
                            'projects' => $item['role'] ?? '',
                            'certifications' => $item['issuer'] ?? '',
                            'organizations' => $item['role'] ?? '',
                            'awards' => $item['issuer'] ?? '',
                            'languages' => lang('CvBuilder.' . ($item['level'] ?? 'professional')),
                            default => '',
                        };
                        $dates = array_filter([
                            $item['start_date'] ?? $item['issued_at'] ?? $item['awarded_at'] ?? null,
                            ($item['is_current'] ?? false) ? lang('CvRender.present') : ($item['end_date'] ?? $item['expires_at'] ?? null),
                        ], static fn (mixed $value): bool => is_string($value) && $value !== '');
                        ?>
                        <div class="cv-entry">
                            <?php if ($title !== ''): ?>
                                <h3><?= esc($title) ?></h3>
                            <?php endif; ?>
                            <?php if ($subtitle !== ''): ?>
                                <p class="cv-subtitle"><?= esc($subtitle) ?></p>
                            <?php endif; ?>
                            <?php if (isset($item['location']) && is_string($item['location']) && $item['location'] !== ''): ?>
                                <p class="cv-meta"><?= esc($item['location']) ?></p>
                            <?php endif; ?>
                            <?php if ($dates !== []): ?>
                                <p class="cv-meta"><?= esc(implode(' - ', $dates)) ?></p>
                            <?php endif; ?>
                            <?php if (is_string($item['description'] ?? null) && trim($item['description']) !== ''): ?>
                                <p><?= nl2br(esc($item['description'])) ?></p>
                            <?php endif; ?>
                            <?php if ($section['key'] === 'projects' && $isSafeUrl($item['url'] ?? null)): ?>
                                <p class="cv-meta"><a href="<?= esc($item['url'], 'attr') ?>" rel="noopener noreferrer nofollow"><?= esc($item['url']) ?></a></p>
                            <?php endif; ?>
                            <?php if ($section['key'] === 'certifications' && $isSafeUrl($item['credential_url'] ?? null)): ?>
                                <p class="cv-meta"><a href="<?= esc($item['credential_url'], 'attr') ?>" rel="noopener noreferrer nofollow"><?= esc(lang('CvRender.credential_url')) ?></a></p>
                            <?php endif; ?>
                            <?php if ($section['key'] === 'projects' && isset($item['tech_stack'])): ?>
                                <?php $renderBullets($item['tech_stack']); ?>
                            <?php endif; ?>
                            <?php if (isset($item['bullets'])): ?>
                                <?php $renderBullets($item['bullets']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </main>
</article>
