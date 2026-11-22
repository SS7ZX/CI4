<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

interface CvAiStreamTransportInterface
{
    /**
     * @param array<string, mixed> $json
     * @param callable(string): void $onChunk
     */
    public function post(string $url, array $json, int $timeoutSeconds, callable $onChunk): int;
}
