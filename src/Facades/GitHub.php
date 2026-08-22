<?php

declare(strict_types=1);

namespace Graft\Facades;

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
use Graft\Enums\Platform\ItemState;
use Graft\Enums\Platform\MergeMethod;
use Graft\Enums\Platform\ReviewEvent;
use Graft\Testing\FakePlatformProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PullRequest createPullRequest(string $repo, string $title, string $body, string $head, string $base, bool $draft = false)
 * @method static PullRequest getPullRequest(string $repo, int $number)
 * @method static Collection<int, PullRequest> listPullRequests(string $repo, ItemState $state = ItemState::Open, ?int $limit = null)
 * @method static PullRequest updatePullRequest(string $repo, int $number, PullRequestUpdate $data)
 * @method static void mergePullRequest(string $repo, int $number, ?MergeMethod $method = null)
 * @method static void closePullRequest(string $repo, int $number)
 * @method static void requestReview(string $repo, int $prNumber, list<string> $reviewers)
 * @method static Collection<int, Review> listReviews(string $repo, int $prNumber, ?int $limit = null)
 * @method static Comment addComment(string $repo, int $number, string $body)
 * @method static Collection<int, Comment> listComments(string $repo, int $number, ?int $limit = null)
 * @method static Comment addReviewComment(string $repo, int $prNumber, string $body, string $commitId, string $path, int $line)
 * @method static Review submitReview(string $repo, int $prNumber, string $body, ReviewEvent $event = ReviewEvent::Comment, list<ReviewCommentInput> $comments = [], ?string $commitId = null)
 * @method static Issue createIssue(string $repo, string $title, string $body, list<string> $labels = [])
 * @method static Issue getIssue(string $repo, int $number)
 * @method static Collection<int, Issue> listIssues(string $repo, ItemState $state = ItemState::Open, ?int $limit = null)
 * @method static Issue updateIssue(string $repo, int $number, IssueUpdate $data)
 * @method static CiStatus getCiStatus(string $repo, string $ref)
 * @method static Collection<int, CheckRun> listCheckRuns(string $repo, string $ref, ?int $limit = null)
 * @method static void addLabels(string $repo, int $number, list<string> $labels)
 * @method static void removeLabel(string $repo, int $number, string $label)
 * @method static Repository getRepository(string $repo)
 * @method static Collection<int, Notification> listNotifications(bool $all = false, ?int $limit = null)
 * @method static Collection<int, PullRequest> searchPullRequests(string $query, ?int $limit = null)
 * @method static Collection<int, Issue> searchIssues(string $query, ?int $limit = null)
 * @method static Collection<int, RepositoryWebhook> listWebhooks(string $repo, ?int $limit = null)
 * @method static RepositoryWebhook createWebhook(string $repo, string $url, list<string> $events, ?string $secret = null)
 *
 * @see PlatformProvider
 */
class GitHub extends Facade
{
    public static function fake(): FakePlatformProvider
    {
        $fake = new FakePlatformProvider;
        static::swap($fake);

        return $fake;
    }

    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return PlatformProvider::class;
    }
}
