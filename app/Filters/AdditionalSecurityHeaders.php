<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AdditionalSecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        return $response
            ->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
            ->setHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->setHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }
}
