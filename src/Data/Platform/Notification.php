<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Carbon\CarbonImmutable;

class Notification
{
    public function __construct(
        public readonly string $id,
        public readonly string $reason,
        public readonly string $subject,
        public readonly string $subjectType,
        public readonly ?string $subjectUrl,
        public readonly string $repo,
        public readonly bool $unread,
        public readonly ?CarbonImmutable $updatedAt = null,
    ) {}

    /** @return array{id: string, reason: string, subject: string, subject_type: string, subject_url: string|null, repo: string, unread: bool, updated_at: string|null} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'subject' => $this->subject,
            'subject_type' => $this->subjectType,
            'subject_url' => $this->subjectUrl,
            'repo' => $this->repo,
            'unread' => $this->unread,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
