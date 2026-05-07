<?php

declare(strict_types=1);

namespace Graft\Exceptions;

class PlatformException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        /** @var array<string, mixed>|null */
        public readonly ?array $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
