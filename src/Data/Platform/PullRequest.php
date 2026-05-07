<?php

declare(strict_types=1);

namespace Graft\Data\Platform;

use Carbon\CarbonImmutable;
use Graft\Contracts\PlatformProvider;
use Illuminate\Support\Collection;

class PullRequest
{
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly string $body,
        public readonly string $state,
        public readonly string $head,
        public readonly string $base,
        public readonly string $url,
        public readonly string $author,
        public readonly bool $draft,
        public readonly bool $mergeable,
        /** @var list<string> */
        public readonly array $labels = [],
        /** @var list<string> */
        public readonly array $reviewers = [],
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?CarbonImmutable $mergedAt = null,
        protected ?PlatformProvider $provider = null,
        protected ?string $repo = null,
    ) {}

    public function withProvider(PlatformProvider $provider, string $repo): static
    {
        $this->provider = $provider;
        $this->repo = $repo;

        return $this;
    }

    public function merge(?string $method = null): void
    {
        $this->provider()->mergePullRequest($this->repo(), $this->number, $method);
    }

    public function close(): void
    {
        $this->provider()->closePullRequest($this->repo(), $this->number);
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): PullRequest
    {
        return $this->provider()->updatePullRequest($this->repo(), $this->number, $data);
    }

    /** @param list<string> $reviewers */
    public function requestReview(array $reviewers): void
    {
        $this->provider()->requestReview($this->repo(), $this->number, $reviewers);
    }

    /** @return Collection<int, Review> */
    public function listReviews(): Collection
    {
        return $this->provider()->listReviews($this->repo(), $this->number);
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

    public function addReviewComment(string $body, string $commitId, string $path, int $line): Comment
    {
        return $this->provider()->addReviewComment($this->repo(), $this->number, $body, $commitId, $path, $line);
    }

    public function getCiStatus(): CiStatus
    {
        return $this->provider()->getCiStatus($this->repo(), $this->head);
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
        return $this->provider ?? throw new \LogicException('PullRequest requires a platform provider. Call withProvider() first.');
    }

    protected function repo(): string
    {
        return $this->repo ?? throw new \LogicException('PullRequest requires a repo. Call withProvider() first.');
    }
}
