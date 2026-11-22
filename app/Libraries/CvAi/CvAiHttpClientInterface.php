<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

use CodeIgniter\HTTP\ResponseInterface;

interface CvAiHttpClientInterface
{
    public function get(string $url, int $timeoutSeconds): ResponseInterface;

    /**
     * @param array<string, mixed> $json
     */
    public function post(string $url, array $json, int $timeoutSeconds): ResponseInterface;
}
