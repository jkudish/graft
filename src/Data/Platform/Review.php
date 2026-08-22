<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Carbon\CarbonImmutable;

readonly class Review
{
    public function __construct(
        public int $id,
        public string $state,
        public string $body,
        public string $author,
        public ?string $commitId = null,
        public ?CarbonImmutable $submittedAt = null,
    ) {}
}
