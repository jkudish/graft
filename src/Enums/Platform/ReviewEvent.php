<?php

declare(strict_types=1);

namespace Graft\Enums\Platform;

enum ReviewEvent: string
{
    case Approve = 'APPROVE';
    case RequestChanges = 'REQUEST_CHANGES';
    case Comment = 'COMMENT';
}
