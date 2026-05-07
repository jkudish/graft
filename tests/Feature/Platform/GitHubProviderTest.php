<?php

declare(strict_types=1);

use Graft\Data\Platform\Issue;
use Graft\Data\Platform\Notification;
use Graft\Data\Platform\PullRequest;
use Graft\Exceptions\PlatformException;
use Graft\Platform\GitHubProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->provider = new GitHubProvider(token: 'test-token', baseUrl: 'https://api.github.com');
});

describe('Pull Requests', function () {
    test('createPullRequest sends POST and returns PullRequest', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls' => Http::response([
                'number' => 1,
                'title' => 'Test PR',
                'body' => 'Description',
                'state' => 'open',
                'head' => ['ref' => 'feature'],
                'base' => ['ref' => 'main'],
                'html_url' => 'https://github.com/owner/repo/pull/1',
                'user' => ['login' => 'jkudish'],
                'draft' => false,
                'mergeable' => true,
                'labels' => [],
                'requested_reviewers' => [],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);

        $pr = $this->provider->createPullRequest('owner/repo', 'Test PR', 'Description', 'feature', 'main');

        expect($pr->number)->toBe(1);
        expect($pr->title)->toBe('Test PR');
        expect($pr->body)->toBe('Description');
        expect($pr->head)->toBe('feature');
        expect($pr->base)->toBe('main');
        expect($pr->draft)->toBe(false);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls'
            && $request['title'] === 'Test PR'
            && $request['head'] === 'feature'
            && $request['base'] === 'main'
            && $request['draft'] === false);
    });

    test('createPullRequest supports draft PRs', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls' => Http::response([
                'number' => 1,
                'title' => 'Draft PR',
                'body' => 'Description',
                'state' => 'open',
                'head' => ['ref' => 'feature'],
                'base' => ['ref' => 'main'],
                'html_url' => 'https://github.com/owner/repo/pull/1',
                'user' => ['login' => 'jkudish'],
                'draft' => true,
                'mergeable' => false,
                'labels' => [],
                'requested_reviewers' => [],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);

        $pr = $this->provider->createPullRequest('owner/repo', 'Draft PR', 'Description', 'feature', 'main', true);

        expect($pr->draft)->toBe(true);

        Http::assertSent(fn ($request) => $request['draft'] === true);
    });

    test('getPullRequest sends GET and returns PullRequest', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1' => Http::response([
                'number' => 1,
                'title' => 'Test PR',
                'body' => 'Description',
                'state' => 'open',
                'head' => ['ref' => 'feature'],
                'base' => ['ref' => 'main'],
                'html_url' => 'https://github.com/owner/repo/pull/1',
                'user' => ['login' => 'jkudish'],
                'draft' => false,
                'mergeable' => true,
                'labels' => [['name' => 'bug'], ['name' => 'enhancement']],
                'requested_reviewers' => [['login' => 'reviewer1']],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-02T00:00:00Z',
                'merged_at' => null,
            ]),
        ]);

        $pr = $this->provider->getPullRequest('owner/repo', 1);

        expect($pr->number)->toBe(1);
        expect($pr->title)->toBe('Test PR');
        expect($pr->labels)->toBe(['bug', 'enhancement']);
        expect($pr->reviewers)->toBe(['reviewer1']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1');
    });

    test('listPullRequests returns collection of PRs', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls?state=open' => Http::response([
                [
                    'number' => 1,
                    'title' => 'First PR',
                    'body' => 'Description 1',
                    'state' => 'open',
                    'head' => ['ref' => 'feature1'],
                    'base' => ['ref' => 'main'],
                    'html_url' => 'https://github.com/owner/repo/pull/1',
                    'user' => ['login' => 'user1'],
                    'draft' => false,
                    'mergeable' => true,
                    'labels' => [],
                    'requested_reviewers' => [],
                    'created_at' => '2026-01-01T00:00:00Z',
                    'updated_at' => '2026-01-01T00:00:00Z',
                ],
                [
                    'number' => 2,
                    'title' => 'Second PR',
                    'body' => 'Description 2',
                    'state' => 'open',
                    'head' => ['ref' => 'feature2'],
                    'base' => ['ref' => 'main'],
                    'html_url' => 'https://github.com/owner/repo/pull/2',
                    'user' => ['login' => 'user2'],
                    'draft' => false,
                    'mergeable' => true,
                    'labels' => [],
                    'requested_reviewers' => [],
                    'created_at' => '2026-01-02T00:00:00Z',
                    'updated_at' => '2026-01-02T00:00:00Z',
                ],
            ]),
        ]);

        $prs = $this->provider->listPullRequests('owner/repo', 'open');

        expect($prs)->toHaveCount(2);
        expect($prs->first()->number)->toBe(1);
        expect($prs->last()->number)->toBe(2);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls?state=open');
    });

    test('updatePullRequest sends PATCH and returns updated PR', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1' => Http::response([
                'number' => 1,
                'title' => 'Updated Title',
                'body' => 'Updated Description',
                'state' => 'open',
                'head' => ['ref' => 'feature'],
                'base' => ['ref' => 'main'],
                'html_url' => 'https://github.com/owner/repo/pull/1',
                'user' => ['login' => 'jkudish'],
                'draft' => false,
                'mergeable' => true,
                'labels' => [],
                'requested_reviewers' => [],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-03T00:00:00Z',
            ]),
        ]);

        $pr = $this->provider->updatePullRequest('owner/repo', 1, ['title' => 'Updated Title', 'body' => 'Updated Description']);

        expect($pr->title)->toBe('Updated Title');
        expect($pr->body)->toBe('Updated Description');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1'
            && $request['title'] === 'Updated Title');
    });

    test('mergePullRequest sends PUT with merge method', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1/merge' => Http::response([
                'sha' => 'abc123',
                'merged' => true,
                'message' => 'Pull Request successfully merged',
            ]),
        ]);

        $this->provider->mergePullRequest('owner/repo', 1, 'squash');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1/merge'
            && $request['merge_method'] === 'squash');
    });

    test('mergePullRequest sends PUT without merge method if not specified', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1/merge' => Http::response([
                'sha' => 'abc123',
                'merged' => true,
                'message' => 'Pull Request successfully merged',
            ]),
        ]);

        $this->provider->mergePullRequest('owner/repo', 1);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1/merge'
            && ! isset($request['merge_method']));
    });

    test('closePullRequest sends PATCH with state closed', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1' => Http::response([
                'number' => 1,
                'title' => 'Test PR',
                'body' => 'Description',
                'state' => 'closed',
                'head' => ['ref' => 'feature'],
                'base' => ['ref' => 'main'],
                'html_url' => 'https://github.com/owner/repo/pull/1',
                'user' => ['login' => 'jkudish'],
                'draft' => false,
                'mergeable' => false,
                'labels' => [],
                'requested_reviewers' => [],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-03T00:00:00Z',
            ]),
        ]);

        $this->provider->closePullRequest('owner/repo', 1);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1'
            && $request['state'] === 'closed');
    });
});

describe('Reviews', function () {
    test('requestReview sends POST with reviewers', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1/requested_reviewers' => Http::response([
                'number' => 1,
                'requested_reviewers' => [
                    ['login' => 'reviewer1'],
                    ['login' => 'reviewer2'],
                ],
            ]),
        ]);

        $this->provider->requestReview('owner/repo', 1, ['reviewer1', 'reviewer2']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1/requested_reviewers'
            && $request['reviewers'] === ['reviewer1', 'reviewer2']);
    });

    test('listReviews returns collection of reviews', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1/reviews' => Http::response([
                [
                    'id' => 1,
                    'state' => 'APPROVED',
                    'body' => 'Looks good!',
                    'user' => ['login' => 'reviewer1'],
                ],
                [
                    'id' => 2,
                    'state' => 'CHANGES_REQUESTED',
                    'body' => 'Needs work',
                    'user' => ['login' => 'reviewer2'],
                ],
            ]),
        ]);

        $reviews = $this->provider->listReviews('owner/repo', 1);

        expect($reviews)->toHaveCount(2);
        expect($reviews->first()->id)->toBe(1);
        expect($reviews->first()->state)->toBe('APPROVED');
        expect($reviews->first()->author)->toBe('reviewer1');
        expect($reviews->last()->state)->toBe('CHANGES_REQUESTED');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1/reviews');
    });
});

describe('Comments', function () {
    test('addComment sends POST and returns comment', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues/1/comments' => Http::response([
                'id' => 1,
                'body' => 'Test comment',
                'user' => ['login' => 'jkudish'],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);

        $comment = $this->provider->addComment('owner/repo', 1, 'Test comment');

        expect($comment->id)->toBe(1);
        expect($comment->body)->toBe('Test comment');
        expect($comment->author)->toBe('jkudish');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues/1/comments'
            && $request['body'] === 'Test comment');
    });

    test('listComments returns collection of comments', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues/1/comments' => Http::response([
                [
                    'id' => 1,
                    'body' => 'First comment',
                    'user' => ['login' => 'user1'],
                    'created_at' => '2026-01-01T00:00:00Z',
                    'updated_at' => '2026-01-01T00:00:00Z',
                ],
                [
                    'id' => 2,
                    'body' => 'Second comment',
                    'user' => ['login' => 'user2'],
                    'created_at' => '2026-01-02T00:00:00Z',
                    'updated_at' => '2026-01-02T00:00:00Z',
                ],
            ]),
        ]);

        $comments = $this->provider->listComments('owner/repo', 1);

        expect($comments)->toHaveCount(2);
        expect($comments->first()->body)->toBe('First comment');
        expect($comments->last()->body)->toBe('Second comment');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues/1/comments');
    });

    test('addReviewComment sends POST with path and line', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/pulls/1/comments' => Http::response([
                'id' => 1,
                'body' => 'Review comment',
                'user' => ['login' => 'jkudish'],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);

        $comment = $this->provider->addReviewComment('owner/repo', 1, 'Review comment', 'abc123', 'src/file.php', 42);

        expect($comment->body)->toBe('Review comment');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/pulls/1/comments'
            && $request['body'] === 'Review comment'
            && $request['commit_id'] === 'abc123'
            && $request['path'] === 'src/file.php'
            && $request['line'] === 42);
    });
});

describe('Issues', function () {
    test('createIssue sends POST and returns issue', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues' => Http::response([
                'number' => 1,
                'title' => 'Test Issue',
                'body' => 'Issue description',
                'state' => 'open',
                'html_url' => 'https://github.com/owner/repo/issues/1',
                'user' => ['login' => 'jkudish'],
                'labels' => [['name' => 'bug']],
                'assignees' => [],
                'created_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);

        $issue = $this->provider->createIssue('owner/repo', 'Test Issue', 'Issue description', ['bug']);

        expect($issue->number)->toBe(1);
        expect($issue->title)->toBe('Test Issue');
        expect($issue->labels)->toBe(['bug']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues'
            && $request['title'] === 'Test Issue'
            && $request['labels'] === ['bug']);
    });

    test('getIssue sends GET and returns issue', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues/1' => Http::response([
                'number' => 1,
                'title' => 'Test Issue',
                'body' => 'Issue description',
                'state' => 'open',
                'html_url' => 'https://github.com/owner/repo/issues/1',
                'user' => ['login' => 'jkudish'],
                'labels' => [['name' => 'bug']],
                'assignees' => [['login' => 'assignee1']],
                'created_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);

        $issue = $this->provider->getIssue('owner/repo', 1);

        expect($issue->number)->toBe(1);
        expect($issue->title)->toBe('Test Issue');
        expect($issue->assignees)->toBe(['assignee1']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues/1');
    });

    test('listIssues returns collection of issues', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues?state=open' => Http::response([
                [
                    'number' => 1,
                    'title' => 'First Issue',
                    'body' => 'Description 1',
                    'state' => 'open',
                    'html_url' => 'https://github.com/owner/repo/issues/1',
                    'user' => ['login' => 'user1'],
                    'labels' => [],
                    'assignees' => [],
                    'created_at' => '2026-01-01T00:00:00Z',
                ],
                [
                    'number' => 2,
                    'title' => 'Second Issue',
                    'body' => 'Description 2',
                    'state' => 'open',
                    'html_url' => 'https://github.com/owner/repo/issues/2',
                    'user' => ['login' => 'user2'],
                    'labels' => [],
                    'assignees' => [],
                    'created_at' => '2026-01-02T00:00:00Z',
                ],
            ]),
        ]);

        $issues = $this->provider->listIssues('owner/repo', 'open');

        expect($issues)->toHaveCount(2);
        expect($issues->first()->number)->toBe(1);
        expect($issues->last()->number)->toBe(2);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues?state=open');
    });

    test('updateIssue sends PATCH and returns updated issue', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues/1' => Http::response([
                'number' => 1,
                'title' => 'Updated Issue',
                'body' => 'Updated description',
                'state' => 'open',
                'html_url' => 'https://github.com/owner/repo/issues/1',
                'user' => ['login' => 'jkudish'],
                'labels' => [],
                'assignees' => [],
                'created_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);

        $issue = $this->provider->updateIssue('owner/repo', 1, ['title' => 'Updated Issue']);

        expect($issue->title)->toBe('Updated Issue');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues/1'
            && $request['title'] === 'Updated Issue');
    });
});

describe('CI and Checks', function () {
    test('getCiStatus returns CiStatus with check runs', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/commits/abc123/status' => Http::response([
                'state' => 'success',
                'statuses' => [],
            ]),
            'api.github.com/repos/owner/repo/commits/abc123/check-runs' => Http::response([
                'check_runs' => [
                    [
                        'id' => 1,
                        'name' => 'CI',
                        'status' => 'completed',
                        'conclusion' => 'success',
                        'html_url' => 'https://github.com/owner/repo/runs/1',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Tests',
                        'status' => 'completed',
                        'conclusion' => 'success',
                        'html_url' => 'https://github.com/owner/repo/runs/2',
                    ],
                ],
            ]),
        ]);

        $ciStatus = $this->provider->getCiStatus('owner/repo', 'abc123');

        expect($ciStatus->state)->toBe('success');
        expect($ciStatus->checkRuns)->toHaveCount(2);
        expect($ciStatus->checkRuns->first()->name)->toBe('CI');
        expect($ciStatus->checkRuns->last()->name)->toBe('Tests');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/commits/abc123/status');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/commits/abc123/check-runs');
    });

    test('listCheckRuns returns collection of check runs', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/commits/abc123/check-runs' => Http::response([
                'check_runs' => [
                    [
                        'id' => 1,
                        'name' => 'CI',
                        'status' => 'completed',
                        'conclusion' => 'success',
                        'html_url' => 'https://github.com/owner/repo/runs/1',
                    ],
                ],
            ]),
        ]);

        $checkRuns = $this->provider->listCheckRuns('owner/repo', 'abc123');

        expect($checkRuns)->toHaveCount(1);
        expect($checkRuns->first()->name)->toBe('CI');
        expect($checkRuns->first()->status)->toBe('completed');
        expect($checkRuns->first()->conclusion)->toBe('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/commits/abc123/check-runs');
    });
});

describe('Labels', function () {
    test('addLabels sends POST with labels array', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues/1/labels' => Http::response([
                ['id' => 1, 'name' => 'bug'],
                ['id' => 2, 'name' => 'enhancement'],
            ]),
        ]);

        $this->provider->addLabels('owner/repo', 1, ['bug', 'enhancement']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues/1/labels'
            && $request['labels'] === ['bug', 'enhancement']);
    });

    test('removeLabel sends DELETE', function () {
        Http::fake([
            'api.github.com/repos/owner/repo/issues/1/labels/bug' => Http::response([], 204),
        ]);

        $this->provider->removeLabel('owner/repo', 1, 'bug');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo/issues/1/labels/bug');
    });
});

describe('Repository', function () {
    test('getRepository returns repository information', function () {
        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response([
                'name' => 'repo',
                'full_name' => 'owner/repo',
                'description' => 'Test repository',
                'default_branch' => 'main',
                'private' => false,
                'html_url' => 'https://github.com/owner/repo',
            ]),
        ]);

        $repo = $this->provider->getRepository('owner/repo');

        expect($repo->name)->toBe('repo');
        expect($repo->fullName)->toBe('owner/repo');
        expect($repo->description)->toBe('Test repository');
        expect($repo->defaultBranch)->toBe('main');
        expect($repo->private)->toBe(false);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/owner/repo');
    });
});

describe('Notifications', function () {
    test('listNotifications fetches notifications', function () {
        Http::fake([
            'api.github.com/notifications*' => Http::response([
                [
                    'id' => '1',
                    'reason' => 'mention',
                    'subject' => ['title' => 'Fix bug', 'type' => 'PullRequest', 'url' => 'https://api.github.com/repos/owner/repo/pulls/1'],
                    'repository' => ['full_name' => 'owner/repo'],
                    'unread' => true,
                    'updated_at' => '2026-01-01T00:00:00Z',
                ],
                [
                    'id' => '2',
                    'reason' => 'subscribed',
                    'subject' => ['title' => 'Add feature', 'type' => 'Issue', 'url' => null],
                    'repository' => ['full_name' => 'owner/other-repo'],
                    'unread' => false,
                    'updated_at' => '2026-01-02T00:00:00Z',
                ],
            ]),
        ]);

        $notifications = $this->provider->listNotifications();

        expect($notifications)->toHaveCount(2);
        expect($notifications->first())->toBeInstanceOf(Notification::class);
        expect($notifications->first()->id)->toBe('1');
        expect($notifications->first()->reason)->toBe('mention');
        expect($notifications->first()->subject)->toBe('Fix bug');
        expect($notifications->first()->subjectType)->toBe('PullRequest');
        expect($notifications->first()->repo)->toBe('owner/repo');
        expect($notifications->first()->unread)->toBeTrue();
        expect($notifications->last()->reason)->toBe('subscribed');
        expect($notifications->last()->subjectUrl)->toBeNull();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/notifications'));
    });

    test('listNotifications passes all parameter', function () {
        Http::fake([
            'api.github.com/notifications*' => Http::response([]),
        ]);

        $this->provider->listNotifications(all: true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/notifications')
            && $request['all'] === true);
    });
});

describe('Search', function () {
    test('searchPullRequests searches pull requests', function () {
        Http::fake([
            'api.github.com/search/issues*' => Http::response([
                'total_count' => 1,
                'items' => [
                    [
                        'number' => 42,
                        'title' => 'Test PR',
                        'body' => 'PR description',
                        'state' => 'open',
                        'html_url' => 'https://github.com/owner/repo/pull/42',
                        'user' => ['login' => 'dev'],
                        'labels' => [['name' => 'enhancement']],
                        'requested_reviewers' => [],
                        'created_at' => '2026-01-01T00:00:00Z',
                        'updated_at' => '2026-01-01T00:00:00Z',
                        'repository_url' => 'https://api.github.com/repos/owner/repo',
                        'draft' => false,
                        'pull_request' => ['html_url' => 'https://github.com/owner/repo/pull/42'],
                    ],
                ],
            ]),
        ]);

        $prs = $this->provider->searchPullRequests('is:open author:jkudish');

        expect($prs)->toHaveCount(1);
        expect($prs->first())->toBeInstanceOf(PullRequest::class);
        expect($prs->first()->number)->toBe(42);
        expect($prs->first()->title)->toBe('Test PR');
        expect($prs->first()->author)->toBe('dev');
        expect($prs->first()->labels)->toBe(['enhancement']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/search/issues')
            && str_contains($request['q'], 'type:pr'));
    });

    test('searchIssues searches issues', function () {
        Http::fake([
            'api.github.com/search/issues*' => Http::response([
                'total_count' => 1,
                'items' => [
                    [
                        'number' => 99,
                        'title' => 'Bug report',
                        'body' => 'Issue description',
                        'state' => 'open',
                        'html_url' => 'https://github.com/owner/repo/issues/99',
                        'user' => ['login' => 'reporter'],
                        'labels' => [['name' => 'bug']],
                        'assignees' => [['login' => 'assignee1']],
                        'created_at' => '2026-01-01T00:00:00Z',
                        'repository_url' => 'https://api.github.com/repos/owner/repo',
                    ],
                ],
            ]),
        ]);

        $issues = $this->provider->searchIssues('is:open label:bug');

        expect($issues)->toHaveCount(1);
        expect($issues->first())->toBeInstanceOf(Issue::class);
        expect($issues->first()->number)->toBe(99);
        expect($issues->first()->title)->toBe('Bug report');
        expect($issues->first()->author)->toBe('reporter');
        expect($issues->first()->labels)->toBe(['bug']);
        expect($issues->first()->assignees)->toBe(['assignee1']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/search/issues')
            && str_contains($request['q'], 'type:issue'));
    });

    test('searchPullRequests handles empty results', function () {
        Http::fake([
            'api.github.com/search/issues*' => Http::response([
                'total_count' => 0,
                'items' => [],
            ]),
        ]);

        $prs = $this->provider->searchPullRequests('nonexistent');

        expect($prs)->toHaveCount(0);
    });
});

describe('Error Handling', function () {
    test('throws PlatformException on 404', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->provider->getPullRequest('owner/repo', 999);
    })->throws(PlatformException::class, 'Not Found');

    test('throws PlatformException on 422 validation error', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Validation Failed'], 422),
        ]);

        $this->provider->createPullRequest('owner/repo', '', '', 'head', 'base');
    })->throws(PlatformException::class, 'Validation Failed');

    test('throws PlatformException on 403 rate limit', function () {
        Http::fake([
            '*' => Http::response(['message' => 'API rate limit exceeded'], 403),
        ]);

        $this->provider->listPullRequests('owner/repo');
    })->throws(PlatformException::class, 'API rate limit exceeded');

    test('PlatformException includes status code and response', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Error', 'errors' => ['field' => 'invalid']], 422),
        ]);

        try {
            $this->provider->getPullRequest('owner/repo', 1);
        } catch (PlatformException $e) {
            expect($e->statusCode)->toBe(422);
            expect($e->response)->toHaveKey('message');
            expect($e->response)->toHaveKey('errors');
        }
    });
});
