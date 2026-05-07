<?php

declare(strict_types=1);

namespace Graft\Exceptions;

use Symfony\Component\Process\Process;

class ProcessException extends GitException
{
    /** @param list<string> $args */
    public static function fromProcess(Process $process, array $args): self
    {
        return new self(
            message: 'Git command failed: git '.implode(' ', $args),
            command: $process->getCommandLine(),
            stderr: trim($process->getErrorOutput()),
            code: $process->getExitCode() ?? 1,
        );
    }
}
