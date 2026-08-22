<?php

declare(strict_types=1);

namespace Graft\Enums\Platform;

enum CheckRunConclusion: string
{
    case ActionRequired = 'action_required';
    case Cancelled = 'cancelled';
    case Failure = 'failure';
    case Neutral = 'neutral';
    case Success = 'success';
    case Skipped = 'skipped';
    case Stale = 'stale';
    case TimedOut = 'timed_out';
    case StartupFailure = 'startup_failure';
}
