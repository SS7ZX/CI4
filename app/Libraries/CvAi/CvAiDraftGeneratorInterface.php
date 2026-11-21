<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

interface CvAiDraftGeneratorInterface
{
    public function generate(string $sanitizedStory): CvAiDraft;
}
