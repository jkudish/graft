<?php

declare(strict_types=1);

namespace Graft\Enums\Git;

enum GitCredentialMode: string
{
    case Baked = 'baked';
    case Env = 'env';
}
