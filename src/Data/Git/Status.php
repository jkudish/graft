<?php

declare(strict_types=1);

namespace Graft\Data\Git;

readonly class Status
{
    /**
     * @param  list<string>  $staged
     * @param  list<string>  $unstaged
     * @param  list<string>  $untracked
     */
    public function __construct(
        public array $staged,
        public array $unstaged,
        public array $untracked,
    ) {}

    public function isClean(): bool
    {
        return ! $this->hasChanges();
    }

    public function hasChanges(): bool
    {
        return count($this->staged) + count($this->unstaged) + count($this->untracked) > 0;
    }
}
