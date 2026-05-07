<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Carbon\CarbonImmutable;
use Graft\Contracts\PlatformProvider;
use Illuminate\Support\Collection;

class Issue
{
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly string $body,
        public readonly string $state,
        public readonly string $url,
        public readonly string $author,
        /** @var list<string> */
        public readonly array $labels = [],
        /** @var list<string> */
        public readonly array $assignees = [],
        public readonly ?CarbonImmutable $createdAt = null,
        protected ?PlatformProvider $provider = null,
        protected ?string $repo = null,
    ) {}

    public function withProvider(PlatformProvider $provider, string $repo): static
    {
        $this->provider = $provider;
        $this->repo = $repo;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): Issue
    {
        return $this->provider()->updateIssue($this->repo(), $this->number, $data);
    }

    public function close(): void
    {
        $this->update(['state' => 'closed']);
    }

    public function addComment(string $body): Comment
    {
        return $this->provider()->addComment($this->repo(), $this->number, $body);
    }

    /** @return Collection<int, Comment> */
    public function listComments(): Collection
    {
        return $this->provider()->listComments($this->repo(), $this->number);
    }

    /** @param list<string> $labels */
    public function addLabels(array $labels): void
    {
        $this->provider()->addLabels($this->repo(), $this->number, $labels);
    }

    public function removeLabel(string $label): void
    {
        $this->provider()->removeLabel($this->repo(), $this->number, $label);
    }

    protected function provider(): PlatformProvider
    {
        return $this->provider ?? throw new \LogicException('Issue requires a platform provider. Call withProvider() first.');
    }

    protected function repo(): string
    {
        return $this->repo ?? throw new \LogicException('Issue requires a repo. Call withProvider() first.');
    }
}
