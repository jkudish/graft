<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

readonly class Review
{
    public function __construct(
        public int $id,
        public string $state,
        public string $body,
        public string $author,
    ) {}
}
