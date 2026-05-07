<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

readonly class Repository
{
    public function __construct(
        public string $name,
        public string $fullName,
        public ?string $description,
        public string $defaultBranch,
        public bool $private,
        public string $url,
    ) {}
}
