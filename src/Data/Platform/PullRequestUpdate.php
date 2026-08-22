<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Graft\Enums\Platform\ItemState;

readonly class PullRequestUpdate
{
    public function __construct(
        public ?string $title = null,
        public ?string $body = null,
        public ?ItemState $state = null,
        public ?string $base = null,
        public ?bool $draft = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'state' => $this->state?->value,
            'base' => $this->base,
            'draft' => $this->draft,
        ], fn (mixed $value): bool => $value !== null);
    }
}
