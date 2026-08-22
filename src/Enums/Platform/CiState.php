<?php

declare(strict_types=1);

namespace Graft\Enums\Platform;

enum CiState: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failure = 'failure';
    case Error = 'error';
}
