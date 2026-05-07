<?php

declare(strict_types=1);

namespace Graft\Testing;

use Carbon\CarbonImmutable;
use Graft\Contracts\PlatformProvider;
use Graft\Data\Platform\CiStatus;
use Graft\Data\Platform\Comment;
use Graft\Data\Platform\Issue;
use Graft\Data\Platform\Notification;
use Graft\Data\Platform\PullRequest;
use Graft\Data\Platform\Repository;
use Graft\Data\Platform\RepositoryWebhook;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

class FakePlatformProvider implements PlatformProvider
{
    /** @var list<array{method: string, args: list<mixed>}> */
    protected array $calls = [];

    /** @var array<string, mixed> */
    protected array $returnValues = [];

    /** @var array<string, \Throwable> */
    protected array $throwables = [];

    // ── Configurable behavior ───────────────────────────────

    public function shouldReturn(string $method, mixed $value): static
    {
        $this->returnValues[$method] = $value;

        return $this;
    }

    public function shouldThrow(string $method, \Throwable $exception): static
    {
        $this->throwables[$method] = $exception;

        return $this;
    }

    // ── Generic assertions ──────────────────────────────────

    public function assertCalled(string $method, ?\Closure $callback = null): void
    {
        $calls = array_filter($this->calls, fn ($call) => $call['method'] === $method);
        Assert::assertNotEmpty($calls, "Expected [{$method}] to be called, but it was not.");

        if ($callback !== null) {
            $matched = array_filter($calls, fn ($call) => $callback($call['args']));
            Assert::assertNotEmpty($matched, "Expected [{$method}] to be called matching callback, but no matching call found.");
        }
    }

    public function assertNotCalled(string $method): void
    {
        $calls = array_filter($this->calls, fn ($call) => $call['method'] === $method);
        Assert::assertEmpty($calls, "Expected [{$method}] not to be called, but it was called ".count($calls).' time(s).');
    }

    public function assertCalledTimes(string $method, int $times): void
    {
        $count = count(array_filter($this->calls, fn ($call) => $call['method'] === $method));
        Assert::assertEquals($times, $count, "Expected [{$method}] to be called {$times} time(s), but was called {$count} time(s).");
    }

    // ── Semantic assertions ─────────────────────────────────

    public function assertPrCreated(string $title, ?string $repo = null): void
    {
        $this->assertCalled('createPullRequest', function ($args) use ($title, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }
            if ($args[1] !== $title) {
                return false;
            }

            return true;
        });
    }

    public function assertPrMerged(int $number, ?string $repo = null): void
    {
        $this->assertCalled('mergePullRequest', function ($args) use ($number, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }

            return $args[1] === $number;
        });
    }

    public function assertPrClosed(int $number, ?string $repo = null): void
    {
        $this->assertCalled('closePullRequest', function ($args) use ($number, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }

            return $args[1] === $number;
        });
    }

    public function assertIssueCreated(string $title, ?string $repo = null): void
    {
        $this->assertCalled('createIssue', function ($args) use ($title, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }
            if ($args[1] !== $title) {
                return false;
            }

            return true;
        });
    }

    public function assertIssueClosed(int $number, ?string $repo = null): void
    {
        $this->assertCalled('updateIssue', function ($args) use ($number, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }
            if ($args[1] !== $number) {
                return false;
            }

            return isset($args[2]['state']) && $args[2]['state'] === 'closed';
        });
    }

    public function assertCommentAdded(?string $bodyContains = null, ?string $repo = null): void
    {
        $this->assertCalled('addComment', function ($args) use ($bodyContains, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }
            if ($bodyContains !== null && ! str_contains($args[2], $bodyContains)) {
                return false;
            }

            return true;
        });
    }

    /** @param  list<string>  $labels */
    public function assertLabelsAdded(array $labels, ?string $repo = null): void
    {
        $this->assertCalled('addLabels', function ($args) use ($labels, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }

            return $args[2] === $labels;
        });
    }

    /** @param  list<string>  $reviewers */
    public function assertReviewRequested(array $reviewers, ?string $repo = null): void
    {
        $this->assertCalled('requestReview', function ($args) use ($reviewers, $repo) {
            if ($repo !== null && $args[0] !== $repo) {
                return false;
            }

            return $args[2] === $reviewers;
        });
    }

    public function assertNotificationsListed(?bool $all = null): void
    {
        $this->assertCalled('listNotifications', $all !== null ? fn ($args) => $args[0] === $all : null);
    }

    public function assertPullRequestsSearched(?string $queryContains = null): void
    {
        $this->assertCalled('searchPullRequests', $queryContains !== null ? fn ($args) => str_contains($args[0], $queryContains) : null);
    }

    public function assertIssuesSearched(?string $queryContains = null): void
    {
        $this->assertCalled('searchIssues', $queryContains !== null ? fn ($args) => str_contains($args[0], $queryContains) : null);
    }

    public function assertWebhookCreated(string $repo, ?string $url = null): void
    {
        $this->assertCalled('createWebhook', function ($args) use ($repo, $url) {
            if ($args[0] !== $repo) {
                return false;
            }
            if ($url !== null && $args[1] !== $url) {
                return false;
            }

            return true;
        });
    }

    public function assertNothingCalled(): void
    {
        Assert::assertEmpty($this->calls, 'Expected no methods to be called, but '.count($this->calls).' call(s) were made.');
    }

    // ── Interface implementation ────────────────────────────

    /**
     * @param  list<mixed>  $args
     */
    protected function record(string $method, array $args): mixed
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        if (isset($this->throwables[$method])) {
            throw $this->throwables[$method];
        }

        return $this->returnValues[$method] ?? null;
    }

    public function createPullRequest(string $repo, string $title, string $body, string $head, string $base, bool $draft = false): PullRequest
    {
        $result = $this->record('createPullRequest', [$repo, $title, $body, $head, $base, $draft]);

        return $result ?? (new PullRequest(
            number: 1,
            title: $title,
            body: $body,
            state: 'open',
            head: $head,
            base: $base,
            url: 'https://github.com/'.$repo.'/pull/1',
            author: 'test-user',
            draft: $draft,
            mergeable: true,
            labels: [],
            reviewers: [],
            createdAt: CarbonImmutable::now(),
        ))->withProvider($this, $repo);
    }

    public function getPullRequest(string $repo, int $number): PullRequest
    {
        $result = $this->record('getPullRequest', [$repo, $number]);

        return $result ?? (new PullRequest(
            number: $number,
            title: 'Test PR',
            body: 'Test body',
            state: 'open',
            head: 'feature-branch',
            base: 'main',
            url: 'https://github.com/'.$repo.'/pull/'.$number,
            author: 'test-user',
            draft: false,
            mergeable: true,
        ))->withProvider($this, $repo);
    }

    public function listPullRequests(string $repo, string $state = 'open'): Collection
    {
        return $this->record('listPullRequests', [$repo, $state]) ?? collect();
    }

    public function updatePullRequest(string $repo, int $number, array $data): PullRequest
    {
        $result = $this->record('updatePullRequest', [$repo, $number, $data]);

        return $result ?? (new PullRequest(
            number: $number,
            title: $data['title'] ?? 'Updated PR',
            body: $data['body'] ?? 'Updated body',
            state: $data['state'] ?? 'open',
            head: 'feature-branch',
            base: 'main',
            url: 'https://github.com/'.$repo.'/pull/'.$number,
            author: 'test-user',
            draft: $data['draft'] ?? false,
            mergeable: true,
        ))->withProvider($this, $repo);
    }

    public function mergePullRequest(string $repo, int $number, ?string $method = null): void
    {
        $this->record('mergePullRequest', [$repo, $number, $method]);
    }

    public function closePullRequest(string $repo, int $number): void
    {
        $this->record('closePullRequest', [$repo, $number]);
    }

    public function requestReview(string $repo, int $prNumber, array $reviewers): void
    {
        $this->record('requestReview', [$repo, $prNumber, $reviewers]);
    }

    public function listReviews(string $repo, int $prNumber): Collection
    {
        return $this->record('listReviews', [$repo, $prNumber]) ?? collect();
    }

    public function addComment(string $repo, int $number, string $body): Comment
    {
        $result = $this->record('addComment', [$repo, $number, $body]);

        return $result ?? new Comment(
            id: 1,
            body: $body,
            author: 'test-user',
            createdAt: CarbonImmutable::now(),
        );
    }

    public function listComments(string $repo, int $number): Collection
    {
        return $this->record('listComments', [$repo, $number]) ?? collect();
    }

    public function addReviewComment(string $repo, int $prNumber, string $body, string $commitId, string $path, int $line): Comment
    {
        $result = $this->record('addReviewComment', [$repo, $prNumber, $body, $commitId, $path, $line]);

        return $result ?? new Comment(
            id: 1,
            body: $body,
            author: 'test-user',
            createdAt: CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<int, array{path: string, line: int, body: string}>  $comments
     * @return array<string, mixed>
     */
    public function submitReview(string $repo, int $prNumber, string $body, string $event = 'COMMENT', array $comments = [], ?string $commitId = null): array
    {
        $result = $this->record('submitReview', [$repo, $prNumber, $body, $event, $comments, $commitId]);

        $response = [
            'body' => $body,
            'event' => $event,
            'comments' => $comments,
        ];

        if ($commitId !== null) {
            $response['commit_id'] = $commitId;
        }

        return $result ?? $response;
    }

    public function createIssue(string $repo, string $title, string $body, array $labels = []): Issue
    {
        $result = $this->record('createIssue', [$repo, $title, $body, $labels]);

        return $result ?? (new Issue(
            number: 1,
            title: $title,
            body: $body,
            state: 'open',
            url: 'https://github.com/'.$repo.'/issues/1',
            author: 'test-user',
            labels: $labels,
            createdAt: CarbonImmutable::now(),
        ))->withProvider($this, $repo);
    }

    public function getIssue(string $repo, int $number): Issue
    {
        $result = $this->record('getIssue', [$repo, $number]);

        return $result ?? (new Issue(
            number: $number,
            title: 'Test Issue',
            body: 'Test body',
            state: 'open',
            url: 'https://github.com/'.$repo.'/issues/'.$number,
            author: 'test-user',
        ))->withProvider($this, $repo);
    }

    public function listIssues(string $repo, string $state = 'open'): Collection
    {
        return $this->record('listIssues', [$repo, $state]) ?? collect();
    }

    public function updateIssue(string $repo, int $number, array $data): Issue
    {
        $result = $this->record('updateIssue', [$repo, $number, $data]);

        return $result ?? (new Issue(
            number: $number,
            title: $data['title'] ?? 'Updated Issue',
            body: $data['body'] ?? 'Updated body',
            state: $data['state'] ?? 'open',
            url: 'https://github.com/'.$repo.'/issues/'.$number,
            author: 'test-user',
        ))->withProvider($this, $repo);
    }

    public function getCiStatus(string $repo, string $ref): CiStatus
    {
        $result = $this->record('getCiStatus', [$repo, $ref]);

        return $result ?? new CiStatus(
            state: 'success',
            checkRuns: collect(),
        );
    }

    public function listCheckRuns(string $repo, string $ref): Collection
    {
        return $this->record('listCheckRuns', [$repo, $ref]) ?? collect();
    }

    public function addLabels(string $repo, int $number, array $labels): void
    {
        $this->record('addLabels', [$repo, $number, $labels]);
    }

    public function removeLabel(string $repo, int $number, string $label): void
    {
        $this->record('removeLabel', [$repo, $number, $label]);
    }

    public function getRepository(string $repo): Repository
    {
        $result = $this->record('getRepository', [$repo]);

        return $result ?? new Repository(
            name: explode('/', $repo)[1] ?? $repo,
            fullName: $repo,
            description: 'Test repository',
            defaultBranch: 'main',
            private: false,
            url: 'https://github.com/'.$repo,
        );
    }

    /** @return Collection<int, Notification> */
    public function listNotifications(bool $all = false): Collection
    {
        return $this->record('listNotifications', [$all]) ?? collect();
    }

    /** @return Collection<int, PullRequest> */
    public function searchPullRequests(string $query): Collection
    {
        return $this->record('searchPullRequests', [$query]) ?? collect();
    }

    /** @return Collection<int, Issue> */
    public function searchIssues(string $query): Collection
    {
        return $this->record('searchIssues', [$query]) ?? collect();
    }

    /** @return Collection<int, RepositoryWebhook> */
    public function listWebhooks(string $repo): Collection
    {
        return $this->record('listWebhooks', [$repo]) ?? collect();
    }

    public function createWebhook(string $repo, string $url, array $events, ?string $secret = null): RepositoryWebhook
    {
        $result = $this->record('createWebhook', [$repo, $url, $events, $secret]);

        return $result ?? new RepositoryWebhook(
            id: rand(1, 99999),
            name: 'web',
            url: $url,
            events: $events,
            active: true,
        );
    }
}
