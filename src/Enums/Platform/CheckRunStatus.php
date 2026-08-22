<?php

declare(strict_types=1);

namespace Graft\Enums\Platform;

enum CheckRunStatus: string
{
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Waiting = 'waiting';
    case Requested = 'requested';
    case Pending = 'pending';
}
