<?php

declare(strict_types=1);

namespace Graft\Enums\Platform;

enum ItemState: string
{
    case Open = 'open';
    case Closed = 'closed';
    case All = 'all';
}
