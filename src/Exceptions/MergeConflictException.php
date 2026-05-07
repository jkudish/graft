<?php

declare(strict_types=1);

namespace Graft\Exceptions;

class MergeConflictException extends GitException
{
    public function __construct(
        string $message,
        /** @var list<string> */
        public readonly array $conflicts = [],
        ?string $command = null,
        ?string $stderr = null,
    ) {
        parent::__construct($message, $command, $stderr);
    }
}
