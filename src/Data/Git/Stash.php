<?php

declare(strict_types=1);

namespace Graft\Data\Git;

readonly class Stash
{
    public function __construct(
        public int $index,
        public string $message,
        public string $hash,
    ) {}
}
