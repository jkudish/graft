<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

readonly class RepositoryWebhook
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
        public array $events,
        public bool $active,
    ) {}
}
