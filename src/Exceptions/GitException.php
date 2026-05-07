<?php

declare(strict_types=1);

namespace Graft\Exceptions;

class GitException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $command = null,
        public readonly ?string $stderr = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
