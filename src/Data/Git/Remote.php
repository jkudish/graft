<?php

declare(strict_types=1);

namespace Graft\Data\Git;

readonly class Remote
{
    public function __construct(
        public string $name,
        public string $fetchUrl,
        public ?string $pushUrl = null,
    ) {}
}
