<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

readonly class ReviewCommentInput
{
    public function __construct(
        public string $path,
        public int $line,
        public string $body,
    ) {}

    /**
     * @return array{path: string, line: int, body: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'line' => $this->line,
            'body' => $this->body,
        ];
    }
}
