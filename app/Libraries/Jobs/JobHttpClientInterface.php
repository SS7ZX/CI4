<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use CodeIgniter\HTTP\ResponseInterface;

interface JobHttpClientInterface
{
    /**
     * @param array<string, mixed> $json
     */
    public function post(string $url, array $json, int $timeoutSeconds): ResponseInterface;
}
