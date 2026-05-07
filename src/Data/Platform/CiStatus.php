<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Illuminate\Support\Collection;

readonly class CiStatus
{
    public function __construct(
        public string $state,
        /** @var Collection<int, CheckRun> */
        public Collection $checkRuns,
    ) {}
}
