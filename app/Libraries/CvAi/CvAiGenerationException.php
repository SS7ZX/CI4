<?php

declare(strict_types=1);

namespace App\Libraries\CvAi;

use RuntimeException;
use Throwable;

final class CvAiGenerationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct($reason, 0, $previous);
    }
}
