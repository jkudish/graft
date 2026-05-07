<?php

declare(strict_types=1);

namespace Graft\Platform;

use Carbon\CarbonImmutable;
use Graft\Contracts\PlatformProvider;
use Graft\Data\Platform\CheckRun;
use Graft\Data\Platform\CiStatus;
use Graft\Data\Platform\Comment;
use Graft\Data\Platform\Issue;
use Graft\Data\Platform\Notification;
use Graft\Data\Platform\PullRequest;
use Graft\Data\Platform\Repository;
use Graft\Data\Platform\RepositoryWebhook;
use Graft\Data\Platform\Review;
use Graft\Exceptions\PlatformException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GitHubProvider implements PlatformProvider
{
    public function __construct(
        protected string $token,
        protected string $baseUrl = 'https://api.github.com',
    ) {}

    // ── Pull Requests ───────────────────────────────────────

    public function createPullRequest(string $repo, string $title, string $body, string $head, string $base, bool $draft = false): PullRequest
    {
        $data = $this->request('post', "/repos/{$repo}/pulls", [
            'title' => $title,
            'body' => $body,
            'head' => $head,
            'base' => $base,
            'draft' => $draft,
        ]);

        return $this->mapPullRequest($data, $repo);
    }

    public function getPullRequest(string $repo, int $number): PullRequest
    {
        $data = $this->request('get', "/repos/{$repo}/pulls/{$number}");

        return $this->mapPullRequest($data, $repo);
    }

    /** @return Collection<int, PullRequest> */
    public function listPullRequests(string $repo, string $state = 'open'): Collection
    {
        $data = $this->request('get', "/repos/{$repo}/pulls", ['state' => $state]);

        return collect($data)->values()->map(fn ($pr) => $this->mapPullRequest($pr, $repo));
    }

    /** @param array<string, mixed> $data */
    public function updatePullRequest(string $repo, int $number, array $data): PullRequest
    {
        $response = $this->request('patch', "/repos/{$repo}/pulls/{$number}", $data);

        return $this->mapPullRequest($response, $repo);
    }

    public function mergePullRequest(string $repo, int $number, ?string $method = null): void
    {
        $data = [];
        if ($method !== null) {
            $data['merge_method'] = $method;
        }

        $this->request('put', "/repos/{$repo}/pulls/{$number}/merge", $data);
    }

    public function closePullRequest(string $repo, int $number): void
    {
        $this->request('patch', "/repos/{$repo}/pulls/{$number}", ['state' => 'closed']);
    }

    // ── Reviews ─────────────────────────────────────────────

    /** @param list<string> $reviewers */
    public function requestReview(string $repo, int $prNumber, array $reviewers): void
    {
        $this->request('post', "/repos/{$repo}/pulls/{$prNumber}/requested_reviewers", [
            'reviewers' => $reviewers,
        ]);
    }

    /** @return Collection<int, Review> */
    public function listReviews(string $repo, int $prNumber): Collection
    {
        $data = $this->request('get', "/repos/{$repo}/pulls/{$prNumber}/reviews");

        return collect($data)->values()->map(fn ($review) => $this->mapReview($review));
    }

    // ── Comments ────────────────────────────────────────────

    public function addComment(string $repo, int $number, string $body): Comment
    {
        $data = $this->request('post', "/repos/{$repo}/issues/{$number}/comments", [
            'body' => $body,
        ]);

        return $this->mapComment($data);
    }

    /** @return Collection<int, Comment> */
    public function listComments(string $repo, int $number): Collection
    {
        $data = $this->request('get', "/repos/{$repo}/issues/{$number}/comments");

        return collect($data)->values()->map(fn ($comment) => $this->mapComment($comment));
    }

    public function addReviewComment(string $repo, int $prNumber, string $body, string $commitId, string $path, int $line): Comment
    {
        $data = $this->request('post', "/repos/{$repo}/pulls/{$prNumber}/comments", [
            'body' => $body,
            'commit_id' => $commitId,
            'path' => $path,
            'line' => $line,
        ]);

        return $this->mapComment($data);
    }

    /**
     * @param  array<int, array{path: string, line: int, body: string}>  $comments
     * @return array<string, mixed>
     */
    public function submitReview(string $repo, int $prNumber, string $body, string $event = 'COMMENT', array $comments = [], ?string $commitId = null): array
    {
        $payload = [
            'body' => $body,
            'event' => $event,
        ];

        if ($commitId !== null) {
            $payload['commit_id'] = $commitId;
        }

        if ($comments !== []) {
            $payload['comments'] = array_map(fn (array $comment): array => [
                'path' => $comment['path'],
                'line' => $comment['line'],
                'body' => $comment['body'],
            ], $comments);
        }

        return $this->request('post', "/repos/{$repo}/pulls/{$prNumber}/reviews", $payload);
    }

    // ── Issues ──────────────────────────────────────────────

    /** @param list<string> $labels */
    public function createIssue(string $repo, string $title, string $body, array $labels = []): Issue
    {
        $data = $this->request('post', "/repos/{$repo}/issues", [
            'title' => $title,
            'body' => $body,
            'labels' => $labels,
        ]);

        return $this->mapIssue($data, $repo);
    }

    public function getIssue(string $repo, int $number): Issue
    {
        $data = $this->request('get', "/repos/{$repo}/issues/{$number}");

        return $this->mapIssue($data, $repo);
    }

    /** @return Collection<int, Issue> */
    public function listIssues(string $repo, string $state = 'open'): Collection
    {
        $data = $this->request('get', "/repos/{$repo}/issues", ['state' => $state]);

        return collect($data)->values()->map(fn ($issue) => $this->mapIssue($issue, $repo));
    }

    /** @param array<string, mixed> $data */
    public function updateIssue(string $repo, int $number, array $data): Issue
    {
        $response = $this->request('patch', "/repos/{$repo}/issues/{$number}", $data);

        return $this->mapIssue($response, $repo);
    }

    // ── CI / Checks ─────────────────────────────────────────

    public function getCiStatus(string $repo, string $ref): CiStatus
    {
        $statusData = $this->request('get', "/repos/{$repo}/commits/{$ref}/status");
        $checkRunsData = $this->request('get', "/repos/{$repo}/commits/{$ref}/check-runs");

        /** @var array<array<string, mixed>> $checkRunsArray */
        $checkRunsArray = $checkRunsData['check_runs'] ?? [];
        $checkRuns = collect($checkRunsArray)->values()->map(fn ($run) => $this->mapCheckRun($run));

        return new CiStatus(
            state: $statusData['state'] ?? 'pending',
            checkRuns: $checkRuns,
        );
    }

    /** @return Collection<int, CheckRun> */
    public function listCheckRuns(string $repo, string $ref): Collection
    {
        $data = $this->request('get', "/repos/{$repo}/commits/{$ref}/check-runs");

        /** @var array<array<string, mixed>> $checkRunsArray */
        $checkRunsArray = $data['check_runs'] ?? [];

        return collect($checkRunsArray)->values()->map(fn ($run) => $this->mapCheckRun($run));
    }

    // ── Labels ──────────────────────────────────────────────

    /** @param list<string> $labels */
    public function addLabels(string $repo, int $number, array $labels): void
    {
        $this->request('post', "/repos/{$repo}/issues/{$number}/labels", [
            'labels' => $labels,
        ]);
    }

    public function removeLabel(string $repo, int $number, string $label): void
    {
        $this->request('delete', "/repos/{$repo}/issues/{$number}/labels/{$label}");
    }

    // ── Repository Info ─────────────────────────────────────

    public function getRepository(string $repo): Repository
    {
        $data = $this->request('get', "/repos/{$repo}");

        return $this->mapRepository($data);
    }

    // ── Notifications & Search ─────────────────────────────

    /** @return Collection<int, Notification> */
    public function listNotifications(bool $all = false): Collection
    {
        $data = $this->request('get', '/notifications', ['all' => $all]);

        return collect($data)->values()->map(fn ($item) => $this->mapNotification($item));
    }

    /** @return Collection<int, PullRequest> */
    public function searchPullRequests(string $query): Collection
    {
        $data = $this->request('get', '/search/issues', ['q' => "type:pr {$query}"]);

        /** @var array<array<string, mixed>> $items */
        $items = $data['items'] ?? [];

        return collect($items)->values()->map(fn ($item) => $this->mapSearchPullRequest($item));
    }

    /** @return Collection<int, Issue> */
    public function searchIssues(string $query): Collection
    {
        $data = $this->request('get', '/search/issues', ['q' => "type:issue {$query}"]);

        /** @var array<array<string, mixed>> $items */
        $items = $data['items'] ?? [];

        return collect($items)->values()->map(fn ($item) => $this->mapSearchIssue($item));
    }

    // ── Webhooks ────────────────────────────────────────────

    /** @return Collection<int, RepositoryWebhook> */
    public function listWebhooks(string $repo): Collection
    {
        $data = $this->request('get', "/repos/{$repo}/hooks");

        /** @var array<array<string, mixed>> $items */
        $items = $data;

        return collect($items)->values()->map(fn ($item) => $this->mapRepositoryWebhook($item));
    }

    public function createWebhook(string $repo, string $url, array $events, ?string $secret = null): RepositoryWebhook
    {
        $payload = [
            'name' => 'web',
            'active' => true,
            'events' => $events,
            'config' => [
                'url' => $url,
                'content_type' => 'json',
            ],
        ];

        if ($secret !== null && $secret !== '') {
            $payload['config']['secret'] = $secret;
        }

        $data = $this->request('post', "/repos/{$repo}/hooks", $payload);

        return $this->mapRepositoryWebhook($data);
    }

    // ── Internal ────────────────────────────────────────────

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function request(string $method, string $url, array $data = []): array
    {
        $response = $this->http()->{$method}($url, $data);

        if ($response->failed()) {
            throw new PlatformException(
                message: $response->json('message', 'GitHub API error'),
                statusCode: $response->status(),
                response: $response->json(),
            );
        }

        return $response->json() ?? [];
    }

    /** @param array<string, mixed> $data */
    protected function mapPullRequest(array $data, string $repo): PullRequest
    {
        return (new PullRequest(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: $data['state'],
            head: $data['head']['ref'],
            base: $data['base']['ref'],
            url: $data['html_url'],
            author: $data['user']['login'],
            draft: $data['draft'] ?? false,
            mergeable: $data['mergeable'] ?? false,
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            reviewers: array_values(array_map(fn ($r) => $r['login'], $data['requested_reviewers'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? CarbonImmutable::parse($data['updated_at']) : null,
            mergedAt: isset($data['merged_at']) ? CarbonImmutable::parse($data['merged_at']) : null,
        ))->withProvider($this, $repo);
    }

    /** @param array<string, mixed> $data */
    protected function mapIssue(array $data, string $repo): Issue
    {
        return (new Issue(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: $data['state'],
            url: $data['html_url'],
            author: $data['user']['login'],
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            assignees: array_values(array_map(fn ($a) => $a['login'], $data['assignees'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
        ))->withProvider($this, $repo);
    }

    /** @param array<string, mixed> $data */
    protected function mapComment(array $data): Comment
    {
        return new Comment(
            id: $data['id'],
            body: $data['body'],
            author: $data['user']['login'],
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? CarbonImmutable::parse($data['updated_at']) : null,
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapReview(array $data): Review
    {
        return new Review(
            id: $data['id'],
            state: $data['state'],
            body: $data['body'] ?? '',
            author: $data['user']['login'],
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapCheckRun(array $data): CheckRun
    {
        return new CheckRun(
            id: $data['id'],
            name: $data['name'],
            status: $data['status'],
            conclusion: $data['conclusion'] ?? null,
            url: $data['html_url'],
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapRepository(array $data): Repository
    {
        return new Repository(
            name: $data['name'],
            fullName: $data['full_name'],
            description: $data['description'] ?? null,
            defaultBranch: $data['default_branch'],
            private: $data['private'],
            url: $data['html_url'],
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapNotification(array $data): Notification
    {
        return new Notification(
            id: (string) $data['id'],
            reason: $data['reason'],
            subject: $data['subject']['title'],
            subjectType: $data['subject']['type'],
            subjectUrl: $data['subject']['url'] ?? null,
            repo: $data['repository']['full_name'],
            unread: $data['unread'],
            updatedAt: isset($data['updated_at']) ? CarbonImmutable::parse($data['updated_at']) : null,
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapSearchPullRequest(array $data): PullRequest
    {
        $repo = $this->extractRepoFromUrl($data['repository_url'] ?? $data['html_url']);

        return (new PullRequest(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: $data['state'],
            head: '',
            base: '',
            url: $data['pull_request']['html_url'] ?? $data['html_url'],
            author: $data['user']['login'],
            draft: $data['draft'] ?? false,
            mergeable: false,
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            reviewers: array_values(array_map(fn ($r) => $r['login'], $data['requested_reviewers'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? CarbonImmutable::parse($data['updated_at']) : null,
        ))->withProvider($this, $repo);
    }

    /** @param array<string, mixed> $data */
    protected function mapSearchIssue(array $data): Issue
    {
        $repo = $this->extractRepoFromUrl($data['repository_url'] ?? $data['html_url']);

        return (new Issue(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: $data['state'],
            url: $data['html_url'],
            author: $data['user']['login'],
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            assignees: array_values(array_map(fn ($a) => $a['login'], $data['assignees'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
        ))->withProvider($this, $repo);
    }

    /** @param array<string, mixed> $data */
    protected function mapRepositoryWebhook(array $data): RepositoryWebhook
    {
        return new RepositoryWebhook(
            id: $data['id'],
            name: $data['name'],
            url: $data['config']['url'] ?? '',
            events: $data['events'] ?? [],
            active: $data['active'] ?? true,
        );
    }

    protected function extractRepoFromUrl(string $url): string
    {
        // Handles both API URLs (https://api.github.com/repos/owner/repo) and HTML URLs (https://github.com/owner/repo/...)
        if (preg_match('#repos/([^/]+/[^/]+)#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#github\.com/([^/]+/[^/]+)#', $url, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
