<?php
$steps = lang('Dashboard.ai_steps');
?>
<nav class="mt-6" aria-label="<?= esc(lang('Dashboard.ai_progress_label')) ?>">
    <?php $progressPercent = count($steps) > 1 ? (($currentStep - 1) / (count($steps) - 1)) * 100 : 100; ?>
    <div class="ai-progress-track mb-3" aria-hidden="true" data-ai-progress data-current-step="<?= (int) $currentStep ?>" data-step-count="<?= count($steps) ?>">
        <span style="--progress-to: <?= esc((string) ($progressPercent / 100)) ?>"></span>
    </div>
    <ol class="grid grid-cols-2 gap-2 sm:grid-cols-5 sm:gap-3">
        <?php foreach ($steps as $index => $label): ?>
            <?php
            $stepNumber = $index + 1;
            $isCurrent = $stepNumber === $currentStep;
            $isComplete = $stepNumber < $currentStep;
            $stepDescription = $isCurrent
                ? lang('Dashboard.ai_step_current')
                : ($isComplete ? lang('Dashboard.ai_step_complete') : lang('Dashboard.ai_step_upcoming'));
            ?>
            <li class="ai-progress-item min-h-14 border-b border-line py-2 text-muted <?= $isComplete ? 'is-complete' : '' ?>" aria-label="<?= esc($stepDescription . ': ' . $label) ?>" <?= $isCurrent ? 'aria-current="step"' : '' ?>>
                <span class="ai-progress-mark">
                    <?php if ($isComplete): ?>
                        <svg class="motion-checkmark size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m3 8 3.2 3.2L13 4.5"></path>
                        </svg>
                    <?php else: ?>
                        <span class="text-xs font-semibold tracking-wide" aria-hidden="true"><?= esc(str_pad((string) $stepNumber, 2, '0', STR_PAD_LEFT)) ?></span>
                    <?php endif; ?>
                </span>
                <span class="text-xs font-semibold leading-5 sm:text-sm"><?= esc($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
