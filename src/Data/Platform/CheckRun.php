<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Graft\Enums\Platform\CheckRunConclusion;
use Graft\Enums\Platform\CheckRunStatus;

readonly class CheckRun
{
    public function __construct(
        public int $id,
        public string $name,
        public CheckRunStatus $status,
        public ?CheckRunConclusion $conclusion,
        public string $url,
    ) {}
}
