<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use CodeIgniter\HTTP\ResponseInterface;

final class CurlJobHttpClient implements JobHttpClientInterface
{
    public function post(string $url, array $json, int $timeoutSeconds): ResponseInterface
    {
        return service('curlrequest')->post($url, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $json,
            'timeout' => $timeoutSeconds,
            'connect_timeout' => $timeoutSeconds,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }
}
