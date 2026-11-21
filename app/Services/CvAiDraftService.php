<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\CvAi\CvAiDraft;
use App\Libraries\CvAi\CvAiDraftGeneratorInterface;
use App\Libraries\CvAi\CvAiGenerationException;
use Config\CvAi;

final class CvAiDraftService
{
    public function __construct(
        private readonly CvAi $config,
        private readonly CvAiDraftGeneratorInterface $generator,
        private readonly CvAiSanitizer $sanitizer = new CvAiSanitizer(),
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }

    /**
     * @param array<string, string> $personalData
     */
    public function sanitizeStory(string $story, array $personalData): string
    {
        return $this->sanitizer->sanitize($story, $personalData);
    }

    public function generate(string $sanitizedStory): CvAiDraft
    {
        if (! $this->isEnabled()) {
            throw new CvAiGenerationException('disabled');
        }

        return $this->generator->generate($sanitizedStory);
    }
}
