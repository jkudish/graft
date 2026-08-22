<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Carbon\CarbonImmutable;
use Graft\Contracts\PlatformProvider;
use Graft\Enums\Platform\ItemState;
use Illuminate\Support\Collection;

class Issue
{
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly string $body,
        public readonly ItemState $state,
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

    public function update(IssueUpdate $data): Issue
    {
        return $this->provider()->updateIssue($this->repo(), $this->number, $data);
    }

    public function close(): void
    {
        $this->update(new IssueUpdate(state: ItemState::Closed));
    }

    public function addComment(string $body): Comment
    {
        return $this->provider()->addComment($this->repo(), $this->number, $body);
    }

    /** @return Collection<int, Comment> */
    public function listComments(?int $limit = null): Collection
    {
        return $this->provider()->listComments($this->repo(), $this->number, $limit);
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
        return $this->provider ?? throw new \LogicException('Issue requires a platform provider.');
    }

    protected function repo(): string
    {
        return $this->repo ?? throw new \LogicException('Issue requires a repo.');
    }
}
