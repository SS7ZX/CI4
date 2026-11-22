<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

interface CvAiDraftGeneratorInterface
{
    public function generate(string $sanitizedStory): CvAiDraft;

    /**
     * @param callable(string): void $onPartial
     */
    public function generateStream(string $sanitizedStory, string $targetRole, callable $onPartial): CvAiDraft;

    /**
     * @param array{
     *     summary: string,
     *     headline: string,
     *     experience: string,
     *     education: string,
     *     skills: string,
     *     additional_facts: string
     * } $context
     */
    public function refineSummary(string $sanitizedStory, array $context, string $instruction): string;

    /**
     * @return array{available: bool, model_installed: bool, reason: string}
     */
    public function status(): array;
}
