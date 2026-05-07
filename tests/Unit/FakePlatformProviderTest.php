<?php

declare(strict_types=1);

use Graft\Data\Platform\Comment;
use Graft\Data\Platform\Issue;
use Graft\Data\Platform\Notification;
use Graft\Data\Platform\PullRequest;
use Graft\Data\Platform\Repository;
use Graft\Facades\GitHub;
use Graft\Testing\FakePlatformProvider;
use Illuminate\Support\Collection;
use PHPUnit\Framework\AssertionFailedError;

test('can create fake platform provider', function () {
    $fake = new FakePlatformProvider;

    expect($fake)->toBeInstanceOf(FakePlatformProvider::class);
});

test('facade fake returns fake platform provider', function () {
    $fake = GitHub::fake();

    expect($fake)->toBeInstanceOf(FakePlatformProvider::class);
});

test('assertCalled passes when method is called', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $fake->assertCalled('createPullRequest');
});

test('assertCalled fails when method is not called', function () {
    $fake = new FakePlatformProvider;

    $fake->assertCalled('createPullRequest');
})->throws(AssertionFailedError::class);

test('assertCalled with callback matches arguments', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $fake->assertCalled('createPullRequest', fn ($args) => $args[1] === 'Test PR');
});

test('assertCalled with callback fails when arguments do not match', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $fake->assertCalled('createPullRequest', fn ($args) => $args[1] === 'Wrong Title');
})->throws(AssertionFailedError::class);

test('assertNotCalled passes when method is not called', function () {
    $fake = new FakePlatformProvider;

    $fake->assertNotCalled('createPullRequest');
});

test('assertNotCalled fails when method is called', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $fake->assertNotCalled('createPullRequest');
})->throws(AssertionFailedError::class);

test('assertCalledTimes passes with correct count', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Test PR 1', 'Body', 'head', 'base');
    $fake->createPullRequest('owner/repo', 'Test PR 2', 'Body', 'head', 'base');

    $fake->assertCalledTimes('createPullRequest', 2);
});

test('assertCalledTimes fails with incorrect count', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $fake->assertCalledTimes('createPullRequest', 2);
})->throws(AssertionFailedError::class);

test('assertPrCreated passes when PR is created', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Feature PR', 'Body', 'head', 'base');

    $fake->assertPrCreated('Feature PR');
});

test('assertPrCreated with repo filter', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Feature PR', 'Body', 'head', 'base');

    $fake->assertPrCreated('Feature PR', 'owner/repo');
});

test('assertPrMerged passes when PR is merged', function () {
    $fake = new FakePlatformProvider;
    $fake->mergePullRequest('owner/repo', 123);

    $fake->assertPrMerged(123);
});

test('assertPrClosed passes when PR is closed', function () {
    $fake = new FakePlatformProvider;
    $fake->closePullRequest('owner/repo', 123);

    $fake->assertPrClosed(123);
});

test('assertIssueCreated passes when issue is created', function () {
    $fake = new FakePlatformProvider;
    $fake->createIssue('owner/repo', 'Bug Report', 'Body');

    $fake->assertIssueCreated('Bug Report');
});

test('assertIssueClosed passes when issue is closed', function () {
    $fake = new FakePlatformProvider;
    $fake->updateIssue('owner/repo', 456, ['state' => 'closed']);

    $fake->assertIssueClosed(456);
});

test('assertCommentAdded passes when comment is added', function () {
    $fake = new FakePlatformProvider;
    $fake->addComment('owner/repo', 123, 'Great work!');

    $fake->assertCommentAdded('Great work!');
});

test('assertLabelsAdded passes when labels are added', function () {
    $fake = new FakePlatformProvider;
    $fake->addLabels('owner/repo', 123, ['bug', 'urgent']);

    $fake->assertLabelsAdded(['bug', 'urgent']);
});

test('assertReviewRequested passes when review is requested', function () {
    $fake = new FakePlatformProvider;
    $fake->requestReview('owner/repo', 123, ['reviewer1', 'reviewer2']);

    $fake->assertReviewRequested(['reviewer1', 'reviewer2']);
});

test('assertNothingCalled passes when no methods are called', function () {
    $fake = new FakePlatformProvider;

    $fake->assertNothingCalled();
});

test('assertNothingCalled fails when methods are called', function () {
    $fake = new FakePlatformProvider;
    $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $fake->assertNothingCalled();
})->throws(AssertionFailedError::class);

test('shouldReturn allows custom return values', function () {
    $fake = new FakePlatformProvider;
    $customPr = new PullRequest(
        number: 999,
        title: 'Custom PR',
        body: 'Custom body',
        state: 'open',
        head: 'custom-head',
        base: 'custom-base',
        url: 'https://github.com/owner/repo/pull/999',
        author: 'custom-author',
        draft: false,
        mergeable: true,
    );

    $fake->shouldReturn('getPullRequest', $customPr);
    $result = $fake->getPullRequest('owner/repo', 999);

    expect($result)->toBe($customPr);
});

test('shouldThrow throws exception', function () {
    $fake = new FakePlatformProvider;
    $exception = new RuntimeException('API error');

    $fake->shouldThrow('createPullRequest', $exception);
    $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');
})->throws(RuntimeException::class, 'API error');

test('createPullRequest returns default pull request', function () {
    $fake = new FakePlatformProvider;
    $pr = $fake->createPullRequest('owner/repo', 'Test PR', 'Test body', 'feature', 'main');

    expect($pr)->toBeInstanceOf(PullRequest::class)
        ->and($pr->title)->toBe('Test PR')
        ->and($pr->body)->toBe('Test body')
        ->and($pr->head)->toBe('feature')
        ->and($pr->base)->toBe('main');
});

test('getPullRequest returns default pull request', function () {
    $fake = new FakePlatformProvider;
    $pr = $fake->getPullRequest('owner/repo', 123);

    expect($pr)->toBeInstanceOf(PullRequest::class)
        ->and($pr->number)->toBe(123);
});

test('createIssue returns default issue', function () {
    $fake = new FakePlatformProvider;
    $issue = $fake->createIssue('owner/repo', 'Bug', 'Description', ['bug']);

    expect($issue)->toBeInstanceOf(Issue::class)
        ->and($issue->title)->toBe('Bug')
        ->and($issue->labels)->toBe(['bug']);
});

test('addComment returns default comment', function () {
    $fake = new FakePlatformProvider;
    $comment = $fake->addComment('owner/repo', 123, 'Test comment');

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and($comment->body)->toBe('Test comment');
});

test('getRepository returns default repository', function () {
    $fake = new FakePlatformProvider;
    $repo = $fake->getRepository('owner/repo');

    expect($repo)->toBeInstanceOf(Repository::class)
        ->and($repo->fullName)->toBe('owner/repo');
});

test('listPullRequests returns empty collection by default', function () {
    $fake = new FakePlatformProvider;
    $prs = $fake->listPullRequests('owner/repo');

    expect($prs)->toBeInstanceOf(Collection::class)
        ->and($prs)->toHaveCount(0);
});

test('active pull request with provider can call methods', function () {
    $fake = new FakePlatformProvider;
    $pr = $fake->createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $pr->addComment('Test comment');

    $fake->assertCommentAdded('Test comment');
});

test('active issue with provider can call methods', function () {
    $fake = new FakePlatformProvider;
    $issue = $fake->createIssue('owner/repo', 'Bug', 'Description');

    $issue->addLabels(['bug']);

    $fake->assertLabelsAdded(['bug']);
});

test('listNotifications returns empty collection by default', function () {
    $fake = new FakePlatformProvider;
    $notifications = $fake->listNotifications();

    expect($notifications)->toBeInstanceOf(Collection::class)
        ->and($notifications)->toHaveCount(0);
});

test('searchPullRequests returns empty collection by default', function () {
    $fake = new FakePlatformProvider;
    $prs = $fake->searchPullRequests('is:open');

    expect($prs)->toBeInstanceOf(Collection::class)
        ->and($prs)->toHaveCount(0);
});

test('searchIssues returns empty collection by default', function () {
    $fake = new FakePlatformProvider;
    $issues = $fake->searchIssues('is:open');

    expect($issues)->toBeInstanceOf(Collection::class)
        ->and($issues)->toHaveCount(0);
});

test('assertNotificationsListed passes when notifications are listed', function () {
    $fake = new FakePlatformProvider;
    $fake->listNotifications();

    $fake->assertNotificationsListed();
});

test('assertNotificationsListed with all filter', function () {
    $fake = new FakePlatformProvider;
    $fake->listNotifications(true);

    $fake->assertNotificationsListed(all: true);
});

test('assertNotificationsListed fails when not called', function () {
    $fake = new FakePlatformProvider;

    $fake->assertNotificationsListed();
})->throws(AssertionFailedError::class);

test('assertPullRequestsSearched passes when PRs are searched', function () {
    $fake = new FakePlatformProvider;
    $fake->searchPullRequests('is:open author:jkudish');

    $fake->assertPullRequestsSearched();
});

test('assertPullRequestsSearched with query filter', function () {
    $fake = new FakePlatformProvider;
    $fake->searchPullRequests('is:open author:jkudish');

    $fake->assertPullRequestsSearched('author:jkudish');
});

test('assertPullRequestsSearched fails when not called', function () {
    $fake = new FakePlatformProvider;

    $fake->assertPullRequestsSearched();
})->throws(AssertionFailedError::class);

test('assertIssuesSearched passes when issues are searched', function () {
    $fake = new FakePlatformProvider;
    $fake->searchIssues('is:open label:bug');

    $fake->assertIssuesSearched();
});

test('assertIssuesSearched with query filter', function () {
    $fake = new FakePlatformProvider;
    $fake->searchIssues('is:open label:bug');

    $fake->assertIssuesSearched('label:bug');
});

test('assertIssuesSearched fails when not called', function () {
    $fake = new FakePlatformProvider;

    $fake->assertIssuesSearched();
})->throws(AssertionFailedError::class);

test('shouldReturn works with listNotifications', function () {
    $fake = new FakePlatformProvider;
    $notifications = collect([
        new Notification(
            id: '1',
            reason: 'mention',
            subject: 'Test',
            subjectType: 'PullRequest',
            subjectUrl: null,
            repo: 'owner/repo',
            unread: true,
        ),
    ]);

    $fake->shouldReturn('listNotifications', $notifications);
    $result = $fake->listNotifications();

    expect($result)->toHaveCount(1);
    expect($result->first())->toBeInstanceOf(Notification::class);
    expect($result->first()->reason)->toBe('mention');
});

test('facade integration with GitHub fake', function () {
    $fake = GitHub::fake();

    GitHub::createPullRequest('owner/repo', 'Test PR', 'Body', 'head', 'base');

    $fake->assertPrCreated('Test PR');
});
