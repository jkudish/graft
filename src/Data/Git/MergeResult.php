<?php

declare(strict_types=1);

namespace Graft\Data\Git;

readonly class MergeResult
{
    /**
     * @param  list<string>  $conflicts
     */
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public array $conflicts = [],
    ) {}
}
