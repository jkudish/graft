<?php

declare(strict_types=1);

namespace Graft\Data\Git;

use Carbon\CarbonImmutable;

readonly class Commit
{
    /**
     * @param  list<string>  $parents
     */
    public function __construct(
        public string $hash,
        public string $shortHash,
        public string $message,
        public string $author,
        public string $email,
        public CarbonImmutable $date,
        public array $parents = [],
    ) {}
}
