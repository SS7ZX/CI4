<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

interface CvAiDraftGeneratorInterface
{
    public function generate(string $sanitizedStory): CvAiDraft;

    /**
     * @return array{available: bool, model_installed: bool, reason: string}
     */
    public function status(): array;
}
