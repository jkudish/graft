<?php

declare(strict_types=1);

namespace Graft\Data\Git;

readonly class Branch
{
    public function __construct(
        public string $name,
        public bool $isCurrent,
        public bool $isRemote,
        public ?string $upstream = null,
        public ?string $head = null,
    ) {}
}
