<?php

declare(strict_types=1);

use Graft\Platform\GitHubProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

uses()->group('integration');

beforeEach(function () {
    if (empty(env('GITHUB_TOKEN'))) {
        $this->markTestSkipped('GITHUB_TOKEN not set — skipping GitHub integration tests');
    }

    $this->provider = new GitHubProvider(
        token: env('GITHUB_TOKEN'),
        baseUrl: 'https://api.github.com',
    );
    $this->repo = env('GRAFT_TEST_REPO', 'jkudish/graft-test-fixture');
    $this->testPrefix = 'graft-test-'.uniqid();
    $this->cleanupBranches = [];
    $this->cleanupPrNumbers = [];
    $this->cleanupIssueNumbers = [];
});

afterEach(function () {
    if (! isset($this->cleanupPrNumbers)) {
        return;
    }

    // Clean up PRs (close them)
    foreach ($this->cleanupPrNumbers as $number) {
        try {
            $this->provider->closePullRequest($this->repo, $number);
        } catch (Throwable) {
        }
    }

    // Clean up issues (close them)
    foreach ($this->cleanupIssueNumbers as $number) {
        try {
            $this->provider->updateIssue($this->repo, $number, ['state' => 'closed']);
        } catch (Throwable) {
        }
    }

    // Clean up remote branches via GitHub API
    foreach ($this->cleanupBranches as $branch) {
        try {
            Http::withToken(env('GITHUB_TOKEN'))
                ->delete("https://api.github.com/repos/{$this->repo}/git/refs/heads/{$branch}");
        } catch (Throwable) {
        }
    }
});

test('repository info', function () {
    $repo = $this->provider->getRepository($this->repo);

    expect($repo->fullName)->toBe($this->repo);
    expect($repo->name)->toBe(explode('/', $this->repo)[1]);
    expect($repo->defaultBranch)->not->toBeEmpty();
});

test('issue lifecycle: create, comment, labels, close', function () {
    $title = "{$this->testPrefix}: Integration test issue";

    $issue = $this->provider->createIssue($this->repo, $title, 'Test body', ['test']);
    $this->cleanupIssueNumbers[] = $issue->number;

    expect($issue->title)->toBe($title);
    expect($issue->state)->toBe('open');
    expect($issue->body)->toBe('Test body');

    // Add comment
    $comment = $issue->addComment('Test comment from integration test');
    expect($comment->body)->toBe('Test comment from integration test');

    // List comments
    $comments = $issue->listComments();
    expect($comments)->toHaveCount(1);

    // Close
    $issue->close();

    $closed = $this->provider->getIssue($this->repo, $issue->number);
    expect($closed->state)->toBe('closed');
});

test('PR lifecycle: create branch, push, create PR, comment, close', function () {
    $branchName = $this->testPrefix;
    $this->cleanupBranches[] = $branchName;

    // Create a branch via GitHub API (from default branch)
    $repo = $this->provider->getRepository($this->repo);
    $defaultBranch = $repo->defaultBranch;

    // Get default branch SHA
    $refResponse = Http::withToken(env('GITHUB_TOKEN'))
        ->get("https://api.github.com/repos/{$this->repo}/git/refs/heads/{$defaultBranch}")
        ->json();
    $sha = $refResponse['object']['sha'];

    // Create branch via API
    Http::withToken(env('GITHUB_TOKEN'))
        ->post("https://api.github.com/repos/{$this->repo}/git/refs", [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);

    // Add a commit to the branch so PR has changes
    $fileContent = base64_encode("Test file for PR {$branchName}\n");
    Http::withToken(env('GITHUB_TOKEN'))
        ->put("https://api.github.com/repos/{$this->repo}/contents/test-{$branchName}.txt", [
            'message' => "Add test file for {$branchName}",
            'content' => $fileContent,
            'branch' => $branchName,
        ]);

    // Create PR
    $pr = $this->provider->createPullRequest(
        $this->repo,
        "{$this->testPrefix}: Integration test PR",
        'Test body',
        $branchName,
        $defaultBranch,
    );
    $this->cleanupPrNumbers[] = $pr->number;

    expect($pr->state)->toBe('open');
    expect($pr->head)->toBe($branchName);
    expect($pr->base)->toBe($defaultBranch);

    // Add comment
    $comment = $pr->addComment('Integration test comment');
    expect($comment->body)->toBe('Integration test comment');

    // List comments
    $comments = $pr->listComments();
    expect($comments->count())->toBeGreaterThanOrEqual(1);

    // Close PR
    $pr->close();

    $closed = $this->provider->getPullRequest($this->repo, $pr->number);
    expect($closed->state)->toBe('closed');
});

test('CI status check', function () {
    $repo = $this->provider->getRepository($this->repo);

    // Get the latest commit on default branch
    $refResponse = Http::withToken(env('GITHUB_TOKEN'))
        ->get("https://api.github.com/repos/{$this->repo}/git/refs/heads/{$repo->defaultBranch}")
        ->json();
    $sha = $refResponse['object']['sha'];

    $status = $this->provider->getCiStatus($this->repo, $sha);

    // Fixture repo may or may not have CI — just verify it returns valid CiStatus
    expect($status->state)->toBeIn(['pending', 'success', 'failure', 'error']);
    expect($status->checkRuns)->toBeInstanceOf(Collection::class);
});

test('list pull requests', function () {
    $prs = $this->provider->listPullRequests($this->repo, 'all');
    expect($prs)->toBeInstanceOf(Collection::class);
});

test('list issues', function () {
    $issues = $this->provider->listIssues($this->repo, 'all');
    expect($issues)->toBeInstanceOf(Collection::class);
});

test('add and remove labels on issue', function () {
    $title = "{$this->testPrefix}: Label test issue";

    $issue = $this->provider->createIssue($this->repo, $title, 'Testing labels');
    $this->cleanupIssueNumbers[] = $issue->number;

    // Add labels
    $issue->addLabels(['bug', 'enhancement']);

    // Fetch again to see labels
    $refreshed = $this->provider->getIssue($this->repo, $issue->number);
    expect($refreshed->labels)->toContain('bug');
    expect($refreshed->labels)->toContain('enhancement');

    // Remove a label
    $issue->removeLabel('bug');

    $refreshed = $this->provider->getIssue($this->repo, $issue->number);
    expect($refreshed->labels)->not->toContain('bug');
    expect($refreshed->labels)->toContain('enhancement');
});

test('update pull request details', function () {
    $branchName = $this->testPrefix;
    $this->cleanupBranches[] = $branchName;

    // Create a branch via GitHub API
    $repo = $this->provider->getRepository($this->repo);
    $defaultBranch = $repo->defaultBranch;

    $refResponse = Http::withToken(env('GITHUB_TOKEN'))
        ->get("https://api.github.com/repos/{$this->repo}/git/refs/heads/{$defaultBranch}")
        ->json();
    $sha = $refResponse['object']['sha'];

    Http::withToken(env('GITHUB_TOKEN'))
        ->post("https://api.github.com/repos/{$this->repo}/git/refs", [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);

    // Add a commit to the branch
    $fileContent = base64_encode("Test file for PR {$branchName}\n");
    Http::withToken(env('GITHUB_TOKEN'))
        ->put("https://api.github.com/repos/{$this->repo}/contents/test-{$branchName}.txt", [
            'message' => "Add test file for {$branchName}",
            'content' => $fileContent,
            'branch' => $branchName,
        ]);

    // Create PR
    $pr = $this->provider->createPullRequest(
        $this->repo,
        "{$this->testPrefix}: Original title",
        'Original body',
        $branchName,
        $defaultBranch,
    );
    $this->cleanupPrNumbers[] = $pr->number;

    // Update PR
    $updated = $pr->update([
        'title' => "{$this->testPrefix}: Updated title",
        'body' => 'Updated body',
    ]);

    expect($updated->title)->toBe("{$this->testPrefix}: Updated title");
    expect($updated->body)->toBe('Updated body');
});

test('list check runs for commit', function () {
    $repo = $this->provider->getRepository($this->repo);

    // Get the latest commit on default branch
    $refResponse = Http::withToken(env('GITHUB_TOKEN'))
        ->get("https://api.github.com/repos/{$this->repo}/git/refs/heads/{$repo->defaultBranch}")
        ->json();
    $sha = $refResponse['object']['sha'];

    $checkRuns = $this->provider->listCheckRuns($this->repo, $sha);

    expect($checkRuns)->toBeInstanceOf(Collection::class);
    // Check runs may or may not exist depending on repo setup
});

test('update issue details', function () {
    $title = "{$this->testPrefix}: Original title";

    $issue = $this->provider->createIssue($this->repo, $title, 'Original body');
    $this->cleanupIssueNumbers[] = $issue->number;

    $updated = $issue->update([
        'title' => "{$this->testPrefix}: Updated title",
        'body' => 'Updated body',
    ]);

    expect($updated->title)->toBe("{$this->testPrefix}: Updated title");
    expect($updated->body)->toBe('Updated body');
});

test('list reviews on pull request', function () {
    $branchName = $this->testPrefix;
    $this->cleanupBranches[] = $branchName;

    // Create a branch and PR
    $repo = $this->provider->getRepository($this->repo);
    $defaultBranch = $repo->defaultBranch;

    $refResponse = Http::withToken(env('GITHUB_TOKEN'))
        ->get("https://api.github.com/repos/{$this->repo}/git/refs/heads/{$defaultBranch}")
        ->json();
    $sha = $refResponse['object']['sha'];

    Http::withToken(env('GITHUB_TOKEN'))
        ->post("https://api.github.com/repos/{$this->repo}/git/refs", [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);

    // Add a commit to the branch
    $fileContent = base64_encode("Test file for PR {$branchName}\n");
    Http::withToken(env('GITHUB_TOKEN'))
        ->put("https://api.github.com/repos/{$this->repo}/contents/test-{$branchName}.txt", [
            'message' => "Add test file for {$branchName}",
            'content' => $fileContent,
            'branch' => $branchName,
        ]);

    $pr = $this->provider->createPullRequest(
        $this->repo,
        "{$this->testPrefix}: Review test PR",
        'Test body',
        $branchName,
        $defaultBranch,
    );
    $this->cleanupPrNumbers[] = $pr->number;

    // List reviews (will be empty for newly created PR)
    $reviews = $pr->listReviews();
    expect($reviews)->toBeInstanceOf(Collection::class);
});
