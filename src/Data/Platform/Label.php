<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

readonly class Label
{
    public function __construct(
        public string $name,
        public string $color,
        public ?string $description = null,
    ) {}
}
