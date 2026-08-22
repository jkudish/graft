<?php

declare(strict_types=1);

namespace Graft\Enums\Platform;

enum MergeMethod: string
{
    case Merge = 'merge';
    case Squash = 'squash';
    case Rebase = 'rebase';
}
