<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Graft\Enums\Platform\CiState;
use Illuminate\Support\Collection;

readonly class CiStatus
{
    /**
     * @param  Collection<int, CheckRun>  $checkRuns
     */
    public function __construct(
        public CiState $state,
        public Collection $checkRuns,
    ) {}
}
