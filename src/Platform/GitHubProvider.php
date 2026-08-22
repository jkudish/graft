<?php

declare(strict_types=1);

namespace Graft\Platform;

use Carbon\CarbonImmutable;
use Graft\Contracts\PlatformProvider;
use Graft\Data\Platform\CheckRun;
use Graft\Data\Platform\CiStatus;
use Graft\Data\Platform\Comment;
use Graft\Data\Platform\Issue;
use Graft\Data\Platform\IssueUpdate;
use Graft\Data\Platform\Notification;
use Graft\Data\Platform\PullRequest;
use Graft\Data\Platform\PullRequestUpdate;
use Graft\Data\Platform\Repository;
use Graft\Data\Platform\RepositoryWebhook;
use Graft\Data\Platform\Review;
use Graft\Data\Platform\ReviewCommentInput;
use Graft\Enums\Platform\CheckRunConclusion;
use Graft\Enums\Platform\CheckRunStatus;
use Graft\Enums\Platform\CiState;
use Graft\Enums\Platform\ItemState;
use Graft\Enums\Platform\MergeMethod;
use Graft\Enums\Platform\ReviewEvent;
use Graft\Exceptions\PlatformException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Throwable;

class GitHubProvider implements PlatformProvider
{
    public const API_VERSION = '2022-11-28';

    public const USER_AGENT = 'jkudish-graft';

    public const PER_PAGE = 100;

    public const MAX_PAGES = 10;

    public const RETRY_ATTEMPTS = 4;

    public function __construct(
        #[SensitiveParameter]
        protected string $token,
        protected string $baseUrl = 'https://api.github.com',
        protected string $apiVersion = self::API_VERSION,
    ) {}

    // ── Pull Requests ───────────────────────────────────────

    #[\Override]
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

    #[\Override]
    public function getPullRequest(string $repo, int $number): PullRequest
    {
        $data = $this->request('get', "/repos/{$repo}/pulls/{$number}");

        return $this->mapPullRequest($data, $repo);
    }

    /** @return Collection<int, PullRequest> */
    #[\Override]
    public function listPullRequests(string $repo, ItemState $state = ItemState::Open, ?int $limit = null): Collection
    {
        $items = $this->paginate("/repos/{$repo}/pulls", ['state' => $state->value], limit: $limit);

        return collect($items)->values()->map(fn (array $pr): PullRequest => $this->mapPullRequest($pr, $repo));
    }

    #[\Override]
    public function updatePullRequest(string $repo, int $number, PullRequestUpdate $data): PullRequest
    {
        $response = $this->request('patch', "/repos/{$repo}/pulls/{$number}", $data->toArray());

        return $this->mapPullRequest($response, $repo);
    }

    #[\Override]
    public function mergePullRequest(string $repo, int $number, ?MergeMethod $method = null): void
    {
        $data = [];
        if ($method !== null) {
            $data['merge_method'] = $method->value;
        }

        $this->request('put', "/repos/{$repo}/pulls/{$number}/merge", $data);
    }

    #[\Override]
    public function closePullRequest(string $repo, int $number): void
    {
        $this->request('patch', "/repos/{$repo}/pulls/{$number}", ['state' => ItemState::Closed->value]);
    }

    // ── Reviews ─────────────────────────────────────────────

    /** @param list<string> $reviewers */
    #[\Override]
    public function requestReview(string $repo, int $prNumber, array $reviewers): void
    {
        $this->request('post', "/repos/{$repo}/pulls/{$prNumber}/requested_reviewers", [
            'reviewers' => $reviewers,
        ]);
    }

    /** @return Collection<int, Review> */
    #[\Override]
    public function listReviews(string $repo, int $prNumber, ?int $limit = null): Collection
    {
        $items = $this->paginate("/repos/{$repo}/pulls/{$prNumber}/reviews", limit: $limit);

        return collect($items)->values()->map(fn (array $review): Review => $this->mapReview($review));
    }

    // ── Comments ────────────────────────────────────────────

    #[\Override]
    public function addComment(string $repo, int $number, string $body): Comment
    {
        $data = $this->request('post', "/repos/{$repo}/issues/{$number}/comments", [
            'body' => $body,
        ]);

        return $this->mapComment($data);
    }

    /** @return Collection<int, Comment> */
    #[\Override]
    public function listComments(string $repo, int $number, ?int $limit = null): Collection
    {
        $items = $this->paginate("/repos/{$repo}/issues/{$number}/comments", limit: $limit);

        return collect($items)->values()->map(fn (array $comment): Comment => $this->mapComment($comment));
    }

    #[\Override]
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
     * @param  list<ReviewCommentInput>  $comments
     */
    #[\Override]
    public function submitReview(string $repo, int $prNumber, string $body, ReviewEvent $event = ReviewEvent::Comment, array $comments = [], ?string $commitId = null): Review
    {
        $payload = [
            'body' => $body,
            'event' => $event->value,
        ];

        if ($commitId !== null) {
            $payload['commit_id'] = $commitId;
        }

        if ($comments !== []) {
            $payload['comments'] = array_map(
                fn (ReviewCommentInput $comment): array => $comment->toArray(),
                $comments,
            );
        }

        return $this->mapReview($this->request('post', "/repos/{$repo}/pulls/{$prNumber}/reviews", $payload));
    }

    // ── Issues ──────────────────────────────────────────────

    /** @param list<string> $labels */
    #[\Override]
    public function createIssue(string $repo, string $title, string $body, array $labels = []): Issue
    {
        $data = $this->request('post', "/repos/{$repo}/issues", [
            'title' => $title,
            'body' => $body,
            'labels' => $labels,
        ]);

        return $this->mapIssue($data, $repo);
    }

    #[\Override]
    public function getIssue(string $repo, int $number): Issue
    {
        $data = $this->request('get', "/repos/{$repo}/issues/{$number}");

        return $this->mapIssue($data, $repo);
    }

    /** @return Collection<int, Issue> */
    #[\Override]
    public function listIssues(string $repo, ItemState $state = ItemState::Open, ?int $limit = null): Collection
    {
        $items = $this->paginate("/repos/{$repo}/issues", ['state' => $state->value], limit: $limit);

        return collect($items)
            ->reject(fn (array $issue): bool => isset($issue['pull_request']))
            ->values()
            ->map(fn (array $issue): Issue => $this->mapIssue($issue, $repo));
    }

    #[\Override]
    public function updateIssue(string $repo, int $number, IssueUpdate $data): Issue
    {
        $response = $this->request('patch', "/repos/{$repo}/issues/{$number}", $data->toArray());

        return $this->mapIssue($response, $repo);
    }

    // ── CI / Checks ─────────────────────────────────────────

    /**
     * Combined Status `state` is the legacy rollup from `/commits/{ref}/status`.
     * Check runs (what GitHub Actions writes) are the source of truth for individual jobs.
     */
    #[\Override]
    public function getCiStatus(string $repo, string $ref): CiStatus
    {
        $statusData = $this->request('get', "/repos/{$repo}/commits/{$ref}/status");
        $checkRuns = $this->listCheckRuns($repo, $ref);

        return new CiStatus(
            state: CiState::tryFrom($statusData['state'] ?? 'pending') ?? CiState::Pending,
            checkRuns: $checkRuns,
        );
    }

    /** @return Collection<int, CheckRun> */
    #[\Override]
    public function listCheckRuns(string $repo, string $ref, ?int $limit = null): Collection
    {
        $items = $this->paginate("/repos/{$repo}/commits/{$ref}/check-runs", itemsKey: 'check_runs', limit: $limit);

        return collect($items)->values()->map(fn (array $run): CheckRun => $this->mapCheckRun($run));
    }

    // ── Labels ──────────────────────────────────────────────

    /** @param list<string> $labels */
    #[\Override]
    public function addLabels(string $repo, int $number, array $labels): void
    {
        $this->request('post', "/repos/{$repo}/issues/{$number}/labels", [
            'labels' => $labels,
        ]);
    }

    #[\Override]
    public function removeLabel(string $repo, int $number, string $label): void
    {
        $this->request('delete', "/repos/{$repo}/issues/{$number}/labels/{$label}");
    }

    // ── Repository Info ─────────────────────────────────────

    #[\Override]
    public function getRepository(string $repo): Repository
    {
        $data = $this->request('get', "/repos/{$repo}");

        return $this->mapRepository($data);
    }

    // ── Notifications & Search ─────────────────────────────

    /** @return Collection<int, Notification> */
    #[\Override]
    public function listNotifications(bool $all = false, ?int $limit = null): Collection
    {
        $items = $this->paginate('/notifications', ['all' => $all], limit: $limit);

        return collect($items)->values()->map(fn (array $item): Notification => $this->mapNotification($item));
    }

    /**
     * Search pull requests. GitHub's search API caps results at 1000.
     *
     * @return Collection<int, PullRequest>
     */
    #[\Override]
    public function searchPullRequests(string $query, ?int $limit = null): Collection
    {
        $items = $this->paginate('/search/issues', ['q' => "type:pr {$query}"], itemsKey: 'items', limit: $limit);

        return collect($items)->values()->map(fn (array $item): PullRequest => $this->mapSearchPullRequest($item));
    }

    /**
     * Search issues. GitHub's search API caps results at 1000.
     *
     * @return Collection<int, Issue>
     */
    #[\Override]
    public function searchIssues(string $query, ?int $limit = null): Collection
    {
        $items = $this->paginate('/search/issues', ['q' => "type:issue {$query}"], itemsKey: 'items', limit: $limit);

        return collect($items)->values()->map(fn (array $item): Issue => $this->mapSearchIssue($item));
    }

    // ── Webhooks ────────────────────────────────────────────

    /** @return Collection<int, RepositoryWebhook> */
    #[\Override]
    public function listWebhooks(string $repo, ?int $limit = null): Collection
    {
        $items = $this->paginate("/repos/{$repo}/hooks", limit: $limit);

        return collect($items)->values()->map(fn (array $item): RepositoryWebhook => $this->mapRepositoryWebhook($item));
    }

    /** @param list<string> $events */
    #[\Override]
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
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => $this->apiVersion,
                'User-Agent' => self::USER_AGENT,
            ])
            ->retry(
                times: self::RETRY_ATTEMPTS,
                sleepMilliseconds: $this->retryDelay(...),
                when: fn (Throwable $exception): bool => $this->shouldRetry($exception),
            );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function send(string $method, string $url, array $data = []): Response
    {
        try {
            /** @var Response $response */
            $response = $this->http()->{$method}($url, $data);
        } catch (RequestException $exception) {
            throw $this->toPlatformException($exception->response);
        }

        if ($response->failed()) {
            throw $this->toPlatformException($response);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function request(string $method, string $url, array $data = []): array
    {
        return $this->send($method, $url, $data)->json() ?? [];
    }

    /**
     * Collect list/search pages, following `Link: rel=next` up to {@see MAX_PAGES}.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function paginate(string $url, array $query = [], ?string $itemsKey = null, ?int $limit = null, int $maxPages = self::MAX_PAGES): array
    {
        $query['per_page'] = min((int) ($query['per_page'] ?? self::PER_PAGE), self::PER_PAGE);

        if ($limit !== null && $limit > 0) {
            $query['per_page'] = min($query['per_page'], $limit);
        }

        $items = [];
        $nextUrl = $url;
        $pageQuery = $query;

        for ($page = 0; $page < $maxPages && $nextUrl !== null; $page++) {
            $response = $this->send('get', $nextUrl, $pageQuery);
            $pageItems = $this->pageItems($response->json(), $itemsKey);

            foreach ($pageItems as $item) {
                $items[] = $item;

                if ($limit !== null && count($items) >= $limit) {
                    return $items;
                }
            }

            $nextUrl = $this->nextPageUrl($response);
            $pageQuery = [];
        }

        return $items;
    }

    /**
     * @param  mixed  $data
     * @return list<array<string, mixed>>
     */
    protected function pageItems(mixed $data, ?string $itemsKey): array
    {
        if (! is_array($data)) {
            return [];
        }

        $pageItems = $itemsKey !== null ? ($data[$itemsKey] ?? []) : $data;

        if (! is_array($pageItems)) {
            return [];
        }

        $items = [];

        foreach (array_values($pageItems) as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function nextPageUrl(Response $response): ?string
    {
        $link = $response->header('Link');

        if (! is_string($link) || $link === '') {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (preg_match('/<([^>]+)>;\s*rel="next"/', trim($part), $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response->status();

        if (in_array($status, [401, 404, 422], true)) {
            return false;
        }

        if (in_array($status, [429, 502, 503, 504], true)) {
            return true;
        }

        return $status === 403 && $this->isRateLimitResponse($exception->response);
    }

    protected function isRateLimitResponse(Response $response): bool
    {
        $message = strtolower((string) $response->json('message', ''));

        if (str_contains($message, 'rate limit')) {
            return true;
        }

        $remaining = $response->header('X-RateLimit-Remaining');

        return $remaining !== '' && (int) $remaining === 0;
    }

    protected function retryDelay(int $attempt, mixed $exception): int
    {
        if ($exception instanceof RequestException) {
            $fromHeaders = $this->retryDelayFromHeaders($exception->response);

            if ($fromHeaders !== null) {
                return $fromHeaders;
            }
        }

        return 100 * (2 ** max(0, $attempt - 1));
    }

    protected function retryDelayFromHeaders(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_string($retryAfter) && $retryAfter !== '') {
            if (is_numeric($retryAfter)) {
                return max(0, (int) $retryAfter) * 1000;
            }

            $when = strtotime($retryAfter);

            if ($when !== false) {
                return max(0, $when - time()) * 1000;
            }
        }

        $reset = $response->header('X-RateLimit-Reset');

        if (is_string($reset) && $reset !== '' && is_numeric($reset)) {
            return max(0, (int) $reset - time()) * 1000;
        }

        return null;
    }

    protected function toPlatformException(Response $response): PlatformException
    {
        /** @var array<string, mixed>|null $body */
        $body = $response->json();

        return new PlatformException(
            message: is_array($body) ? (string) ($body['message'] ?? 'GitHub API error') : 'GitHub API error',
            statusCode: $response->status(),
            response: is_array($body) ? $body : null,
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapPullRequest(array $data, string $repo): PullRequest
    {
        return new PullRequest(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: ItemState::tryFrom($data['state'] ?? 'open') ?? ItemState::Open,
            head: $data['head']['ref'],
            base: $data['base']['ref'],
            url: $data['html_url'],
            author: $data['user']['login'],
            draft: $data['draft'] ?? false,
            mergeable: array_key_exists('mergeable', $data) ? $data['mergeable'] : null,
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            reviewers: array_values(array_map(fn ($r) => $r['login'], $data['requested_reviewers'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? CarbonImmutable::parse($data['updated_at']) : null,
            mergedAt: isset($data['merged_at']) ? CarbonImmutable::parse($data['merged_at']) : null,
            provider: $this,
            repo: $repo,
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapIssue(array $data, string $repo): Issue
    {
        return new Issue(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: ItemState::tryFrom($data['state'] ?? 'open') ?? ItemState::Open,
            url: $data['html_url'],
            author: $data['user']['login'],
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            assignees: array_values(array_map(fn ($a) => $a['login'], $data['assignees'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            provider: $this,
            repo: $repo,
        );
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
            author: $data['user']['login'] ?? '',
            commitId: $data['commit_id'] ?? null,
            submittedAt: isset($data['submitted_at']) ? CarbonImmutable::parse($data['submitted_at']) : null,
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapCheckRun(array $data): CheckRun
    {
        $conclusion = $data['conclusion'] ?? null;

        return new CheckRun(
            id: $data['id'],
            name: $data['name'],
            status: CheckRunStatus::tryFrom($data['status'] ?? '') ?? CheckRunStatus::Queued,
            conclusion: is_string($conclusion) ? CheckRunConclusion::tryFrom($conclusion) : null,
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

        return new PullRequest(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: ItemState::tryFrom($data['state'] ?? 'open') ?? ItemState::Open,
            head: '',
            base: '',
            url: $data['pull_request']['html_url'] ?? $data['html_url'],
            author: $data['user']['login'],
            draft: $data['draft'] ?? false,
            mergeable: null,
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            reviewers: array_values(array_map(fn ($r) => $r['login'], $data['requested_reviewers'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? CarbonImmutable::parse($data['updated_at']) : null,
            provider: $this,
            repo: $repo,
        );
    }

    /** @param array<string, mixed> $data */
    protected function mapSearchIssue(array $data): Issue
    {
        $repo = $this->extractRepoFromUrl($data['repository_url'] ?? $data['html_url']);

        return new Issue(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'] ?? '',
            state: ItemState::tryFrom($data['state'] ?? 'open') ?? ItemState::Open,
            url: $data['html_url'],
            author: $data['user']['login'],
            labels: array_values(array_map(fn ($l) => $l['name'], $data['labels'] ?? [])),
            assignees: array_values(array_map(fn ($a) => $a['login'], $data['assignees'] ?? [])),
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            provider: $this,
            repo: $repo,
        );
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
