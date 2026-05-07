<?php

declare(strict_types=1);

namespace Graft\Data\Git;

use Carbon\CarbonImmutable;

readonly class Blame
{
    public function __construct(
        public int $lineNumber,
        public string $hash,
        public string $author,
        public CarbonImmutable $date,
        public string $content,
    ) {}
}
