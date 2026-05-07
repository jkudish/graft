<?php

declare(strict_types=1);

namespace Graft\Data\Git;

readonly class Worktree
{
    public function __construct(
        public string $path,
        public ?string $branch,
        public string $head,
        public bool $isBare,
    ) {}
}
