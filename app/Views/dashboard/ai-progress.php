<?php
$steps = lang('Dashboard.ai_steps');
?>
<nav class="mt-6" aria-label="<?= esc(lang('Dashboard.ai_progress_label')) ?>">
    <ol class="grid grid-cols-2 gap-2 sm:grid-cols-5 sm:gap-3">
        <?php foreach ($steps as $index => $label): ?>
            <?php
            $stepNumber = $index + 1;
            $isCurrent = $stepNumber === $currentStep;
            $isComplete = $stepNumber < $currentStep;
            $stepStyle = $isCurrent
                ? 'border-accent-700 bg-accent-700 text-white'
                : ($isComplete
                    ? 'border-accent-700 bg-accent-50 text-accent-900'
                    : 'border-line bg-paper text-muted');
            ?>
            <li class="flex min-h-16 items-center gap-2 rounded-md border px-3 py-2 sm:flex-col sm:items-start sm:justify-center sm:gap-1 sm:px-3 <?= esc($stepStyle) ?>" <?= $isCurrent ? 'aria-current="step"' : '' ?>>
                <span class="text-xs font-semibold tracking-wide" aria-hidden="true"><?= esc(str_pad((string) $stepNumber, 2, '0', STR_PAD_LEFT)) ?></span>
                <span class="text-xs font-semibold leading-5 sm:text-sm"><?= esc($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
