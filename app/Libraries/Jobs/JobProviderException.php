<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use RuntimeException;

final class JobProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
