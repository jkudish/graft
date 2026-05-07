<?php

declare(strict_types=1);

namespace Graft\Contracts;

use Graft\Data\Platform\CheckRun;
use Graft\Data\Platform\CiStatus;
use Graft\Data\Platform\Comment;
use Graft\Data\Platform\Issue;
use Graft\Data\Platform\Notification;
use Graft\Data\Platform\PullRequest;
use Graft\Data\Platform\Repository;
use Graft\Data\Platform\RepositoryWebhook;
use Graft\Data\Platform\Review;
use Illuminate\Support\Collection;

interface PlatformProvider
{
    // Pull Requests
    public function createPullRequest(string $repo, string $title, string $body, string $head, string $base, bool $draft = false): PullRequest;

    public function getPullRequest(string $repo, int $number): PullRequest;

    /**
     * @return Collection<int, PullRequest>
     */
    public function listPullRequests(string $repo, string $state = 'open'): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePullRequest(string $repo, int $number, array $data): PullRequest;

    public function mergePullRequest(string $repo, int $number, ?string $method = null): void;

    public function closePullRequest(string $repo, int $number): void;

    // Reviews
    /**
     * @param  list<string>  $reviewers
     */
    public function requestReview(string $repo, int $prNumber, array $reviewers): void;

    /**
     * @return Collection<int, Review>
     */
    public function listReviews(string $repo, int $prNumber): Collection;

    // Comments
    public function addComment(string $repo, int $number, string $body): Comment;

    /**
     * @return Collection<int, Comment>
     */
    public function listComments(string $repo, int $number): Collection;

    public function addReviewComment(string $repo, int $prNumber, string $body, string $commitId, string $path, int $line): Comment;

    /**
     * Submit a review on a pull request with optional inline comments.
     *
     * @param  string  $repo  Repository in owner/repo format
     * @param  int  $prNumber  Pull request number
     * @param  string  $body  Review body text
     * @param  string  $event  Review event: APPROVE, REQUEST_CHANGES, or COMMENT
     * @param  array<int, array{path: string, line: int, body: string}>  $comments  Inline comments
     * @return array<string, mixed> The API response
     */
    public function submitReview(string $repo, int $prNumber, string $body, string $event = 'COMMENT', array $comments = [], ?string $commitId = null): array;

    // Issues
    /**
     * @param  list<string>  $labels
     */
    public function createIssue(string $repo, string $title, string $body, array $labels = []): Issue;

    public function getIssue(string $repo, int $number): Issue;

    /**
     * @return Collection<int, Issue>
     */
    public function listIssues(string $repo, string $state = 'open'): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIssue(string $repo, int $number, array $data): Issue;

    // CI/Checks
    public function getCiStatus(string $repo, string $ref): CiStatus;

    /**
     * @return Collection<int, CheckRun>
     */
    public function listCheckRuns(string $repo, string $ref): Collection;

    // Labels
    /**
     * @param  list<string>  $labels
     */
    public function addLabels(string $repo, int $number, array $labels): void;

    public function removeLabel(string $repo, int $number, string $label): void;

    // Repository
    public function getRepository(string $repo): Repository;

    // Notifications & Search
    /** @return Collection<int, Notification> */
    public function listNotifications(bool $all = false): Collection;

    /** @return Collection<int, PullRequest> */
    public function searchPullRequests(string $query): Collection;

    /** @return Collection<int, Issue> */
    public function searchIssues(string $query): Collection;

    // Webhooks
    /** @return Collection<int, RepositoryWebhook> */
    public function listWebhooks(string $repo): Collection;

    /**
     * @param  list<string>  $events
     */
    public function createWebhook(string $repo, string $url, array $events, ?string $secret = null): RepositoryWebhook;
}
