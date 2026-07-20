<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Exceptions;

use RuntimeException;

final class BridgeException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $statusCode,
        string $message = ''
    ) {
        parent::__construct($message ?: $errorCode);
    }
}
