<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

readonly class CheckRun
{
    public function __construct(
        public int $id,
        public string $name,
        public string $status,
        public ?string $conclusion,
        public string $url,
    ) {}
}
