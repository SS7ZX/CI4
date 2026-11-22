<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

use CodeIgniter\HTTP\ResponseInterface;

interface CvAiEventStreamInterface
{
    public function begin(ResponseInterface $response): void;

    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $event, array $payload): bool;
}
