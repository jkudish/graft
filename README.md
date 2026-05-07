<p align="center">
  <img src="art/banner.png" alt="Graft — Git and GitHub for Laravel" width="100%">
</p>

<h1 align="center">Graft</h1>

<p align="center">
  <strong>Branch, commit, and ship from your Laravel app — without ever shelling out by hand or hand-rolling the GitHub API.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/jkudish/graft"><img src="https://img.shields.io/packagist/v/jkudish/graft.svg?style=flat-square" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/jkudish/graft"><img src="https://img.shields.io/packagist/dt/jkudish/graft.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="LICENSE"><img src="https://img.shields.io/packagist/l/jkudish/graft.svg?style=flat-square" alt="License"></a>
  <a href="composer.json"><img src="https://img.shields.io/packagist/php-v/jkudish/graft.svg?style=flat-square" alt="PHP Version"></a>
</p>

---

Graft is the missing git-and-platform layer for Laravel. It puts everything you'd reach for `exec('git ...')` or a hand-rolled GitHub HTTP client to do behind two clean facades — `Git` and `GitHub` — and returns typed, readonly DTOs instead of raw output or arrays.

It was built for the kind of app that needs to *do real work* in real repos: tools that orchestrate AI coding agents, dashboards that open and merge PRs on your behalf, internal automation that branches, commits, and ships. It scales from "create a tag" to "spin up a worktree, run a series of changes, open a PR, request review, watch CI, merge, and clean up" — without you ever leaving Laravel idioms.

Three things make it pleasant:

- **`Git::repo($path)`** scopes both git and platform calls to a single repository — no more threading `$repoPath` through every method, and `owner/repo` is auto-detected from the origin remote.
- **Active objects.** `$pr->merge()`, `$pr->requestReview([...])`, `$issue->close()` — DTOs returned from the platform carry their actions with them.
- **Tests that read like specs.** `Git::fake()` and `GitHub::fake()` return recording fakes with semantic assertions (`assertBranchCreated`, `assertPrCreated`, `assertReviewRequested`) — no Mockery boilerplate, no string-matching command lines.

```php
use Graft\Facades\Git;

$repo = Git::repo('/path/to/project');

$repo->checkout('feature/payments', create: true);
$repo->add('.');
$repo->commit('Add Stripe webhook handler');
$repo->push(setUpstream: true);

$pr = $repo->createPullRequest(
    title: 'Add Stripe webhook handler',
    body: 'Closes #142',
    head: 'feature/payments',
    base: 'main',
);

$pr->requestReview(['teammate']);
$pr->addLabels(['enhancement']);
```

## What's in the box

- **`Git` facade** — branches, commits, index, remotes, merge, rebase, cherry-pick, tags, stash, worktrees, blame, clean.
- **`GitHub` facade** — pull requests, issues, reviews, comments, CI status, labels, repository info.
- **Scoped repository** — `Git::repo($path)` binds both surfaces to a single repo and auto-detects `owner/repo` from the origin remote.
- **Typed DTOs** — `Branch`, `Commit`, `Status`, `MergeResult`, `Stash`, `Worktree`, `PullRequest`, `Issue`, `Review`, `CheckRun`, `CiStatus`, and more — all readonly, all with named properties.
- **Recording fakes** — `Git::fake()` and `GitHub::fake()` swap the real implementations for in-memory recorders with semantic assertions and configurable return values / exceptions.
- **Errors with context** — `MergeConflictException` exposes the conflicting files; `PlatformException` exposes the status code and the response body.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- `git` binary on `PATH`

## Installation

```bash
composer require jkudish/graft
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=graft-config
```

Set your GitHub token in `.env`:

```dotenv
GITHUB_TOKEN=ghp_your_token_here
```

## Table of Contents

- [Scoped Repository](#scoped-repository) — the recommended entry point
- [Git Facade](#git-facade) — local repository operations
- [GitHub Facade](#github-facade) — platform operations
- [Active Objects](#active-objects) — methods on PRs and Issues
- [Testing](#testing) — `Git::fake()` and `GitHub::fake()`
- [Error Handling](#error-handling)
- [Configuration](#configuration)
- [DTOs](#data-transfer-objects)

## Scoped Repository

`Git::repo($path)` returns a `ScopedRepository` that binds both git and platform operations to a single path. It's the most ergonomic way to use Graft.

```php
$repo = Git::repo('/path/to/project');

$repo->currentBranch();
$repo->checkout('feature/x', create: true);
$repo->add('.');
$repo->commit('Changes');
$repo->push(setUpstream: true);

$pr     = $repo->createPullRequest(title: 'Feature X', body: '...', head: 'feature/x', base: 'main');
$issues = $repo->listIssues();
$ci     = $repo->getCiStatus('feature/x');
```

The scoped repository detects `owner/repo` from the origin remote URL (HTTPS or SSH) and resolves the configured platform provider automatically.

## Git Facade

The `Git` facade proxies all methods on `GitManager`. Each method takes `$repoPath` as its first argument — or use `Git::repo($path)` to drop it entirely.

### Branches

```php
Git::branches($path);              // Collection<Branch>
Git::currentBranch($path);         // "main"
Git::branchExists($path, 'dev');

Git::createBranch($path, 'feature/x', 'main');
Git::checkout($path, 'feature/x');
Git::deleteBranch($path, 'feature/x', force: true);
```

### Commits and Index

```php
Git::add($path, ['src/File.php']);
Git::add($path);                                         // stage everything

$commit = Git::commit($path, 'Fix the thing');
// Commit { hash, shortHash, message, author, email, date, parents }

Git::log($path, limit: 5);                               // Collection<Commit>
Git::show($path);                                        // HEAD commit
Git::status($path);                                      // Status { staged, unstaged, untracked }
Git::diff($path, staged: true);                          // string
```

### Remotes and Syncing

```php
Git::fetch($path, prune: true);
Git::pull($path, 'origin', 'main');
Git::push($path, 'origin', 'main', setUpstream: true);

Git::remotes($path);                                     // Collection<Remote>
Git::addRemote($path, 'upstream', 'https://github.com/org/repo.git');
Git::removeRemote($path, 'upstream');
```

### Merge, Rebase, Cherry-pick

```php
$result = Git::merge($path, 'feature/x', noFf: true);
// MergeResult { success, message, conflicts }

Git::rebase($path, 'main');
Git::cherryPick($path, ['abc123', 'def456']);

Git::mergeAbort($path);
Git::rebaseAbort($path);
Git::cherryPickAbort($path);
```

### Tags, Stash, Worktrees

```php
Git::tags($path);                                        // Collection<string>
Git::createTag($path, 'v1.0.0', message: 'Release 1.0');
Git::deleteTag($path, 'v1.0.0');

Git::stash($path, message: 'WIP', includeUntracked: true);
Git::stashPop($path);
Git::stashList($path);                                   // Collection<Stash>

$wt = Git::addWorktree($path, '/tmp/worktree', 'feature/x', createBranch: true);
Git::listWorktrees($path);                               // Collection<Worktree>
Git::removeWorktree($path, '/tmp/worktree', force: true);
```

### Repository and Config

```php
Git::init('/path/to/new-repo');
Git::clone('https://github.com/org/repo.git', '/path/to/dest', branch: 'main');
Git::isRepository('/some/path');

Git::getConfig($path, 'user.name');
Git::setConfig($path, 'user.name', 'Graft');

Git::blame($path, 'src/File.php');                       // Collection<Blame>
Git::clean($path, directories: true);
```

## GitHub Facade

The `GitHub` facade works with any repository by passing `owner/repo` directly.

### Pull Requests

```php
$pr  = GitHub::createPullRequest('owner/repo', 'Title', 'Body', 'feature', 'main', draft: true);
$pr  = GitHub::getPullRequest('owner/repo', 42);
$prs = GitHub::listPullRequests('owner/repo', state: 'open');

GitHub::updatePullRequest('owner/repo', 42, ['title' => 'New title']);
GitHub::mergePullRequest('owner/repo', 42, method: 'squash');
GitHub::closePullRequest('owner/repo', 42);
```

### Issues

```php
$issue = GitHub::createIssue('owner/repo', 'Bug', 'Details', labels: ['bug']);
$issue = GitHub::getIssue('owner/repo', 10);

GitHub::listIssues('owner/repo', state: 'open');
GitHub::updateIssue('owner/repo', 10, ['state' => 'closed']);
```

### Reviews, Comments, CI, Labels

```php
GitHub::requestReview('owner/repo', 42, ['reviewer1']);
GitHub::listReviews('owner/repo', 42);                   // Collection<Review>

GitHub::addComment('owner/repo', 42, 'Looks good!');
GitHub::addReviewComment('owner/repo', 42, 'Nit', 'abc123', 'src/File.php', 15);

GitHub::getCiStatus('owner/repo', 'abc123');             // CiStatus { state, checkRuns }
GitHub::listCheckRuns('owner/repo', 'abc123');

GitHub::addLabels('owner/repo', 42, ['ready-for-review']);
GitHub::removeLabel('owner/repo', 42, 'wip');
```

### Repository Info

```php
GitHub::getRepository('owner/repo');
// Repository { name, fullName, description, defaultBranch, private, url }
```

## Active Objects

`PullRequest` and `Issue` DTOs returned from the platform provider carry a reference back to the provider so you can act on them directly.

```php
$pr = GitHub::getPullRequest('owner/repo', 42);

$pr->merge(method: 'squash');
$pr->close();
$pr->update(['title' => 'Updated']);
$pr->requestReview(['teammate']);
$pr->addComment('Ship it!');
$pr->addReviewComment('Fix this', 'abc123', 'src/File.php', 10);
$pr->getCiStatus();
$pr->addLabels(['approved']);

$issue = GitHub::getIssue('owner/repo', 10);

$issue->close();
$issue->update(['title' => 'Updated']);
$issue->addComment('Fixed in #42');
$issue->addLabels(['resolved']);
```

## Testing

Both facades have `fake()` methods that swap in a recording fake with semantic assertions.

```php
use Graft\Facades\Git;
use Graft\Facades\GitHub;

it('creates a feature branch, opens a PR, and requests review', function () {
    $git = Git::fake();
    $github = GitHub::fake();

    // ...your code under test...

    $git->assertBranchCreated('feature/x');
    $git->assertCommitted('Add feature');
    $git->assertPushed('feature/x');

    $github->assertPrCreated('Add feature');
    $github->assertReviewRequested(['teammate']);
    $github->assertLabelsAdded(['enhancement']);
});
```

### Git Assertions

```php
// Generic
$fake->assertCalled('commit');
$fake->assertCalled('commit', fn ($args) => str_contains($args[1], 'fix'));
$fake->assertNotCalled('push');
$fake->assertCalledTimes('fetch', 2);

// Semantic
$fake->assertBranchCreated('name');
$fake->assertCheckedOut('branch');
$fake->assertCommitted('message substring');
$fake->assertPushed('branch');
$fake->assertPulled();
$fake->assertFetched();
$fake->assertMerged('branch');
$fake->assertTagCreated('v1.0.0');
$fake->assertCloned('https://...');
$fake->assertInitialized('/path');
$fake->assertWorktreeAdded('/path');
$fake->assertWorktreeRemoved('/path');
$fake->assertStashed();

// Negative
$fake->assertNothingPushed();
$fake->assertNothingCommitted();
$fake->assertNothingCalled();
```

### GitHub Assertions

```php
$fake->assertPrCreated('title');
$fake->assertPrMerged(42);
$fake->assertPrClosed(42);
$fake->assertIssueCreated('title');
$fake->assertIssueClosed(10);
$fake->assertCommentAdded('body substring');
$fake->assertLabelsAdded(['label1']);
$fake->assertReviewRequested(['reviewer1']);
$fake->assertNothingCalled();
```

### Configuring Return Values and Errors

```php
$fake = Git::fake();
$fake->shouldReturn('currentBranch', 'develop');
$fake->shouldReturn('status', new Status(staged: ['file.php'], unstaged: [], untracked: []));
$fake->shouldThrow('push', new ProcessException('Remote rejected'));
```

## Error Handling

```
RuntimeException
├── GitException                 // base for all git errors (command + stderr context)
│   ├── ProcessException         // git process failed
│   ├── BranchException          // branch operation failed
│   ├── MergeConflictException   // exposes conflicts: list<string>
│   ├── WorktreeException
│   └── TagException
└── PlatformException            // exposes statusCode + response
```

```php
use Graft\Exceptions\MergeConflictException;
use Graft\Exceptions\PlatformException;

try {
    Git::merge($path, 'feature/x');
} catch (MergeConflictException $e) {
    $e->conflicts;                // list<string>
    Git::mergeAbort($path);
}

try {
    GitHub::mergePullRequest('owner/repo', 42);
} catch (PlatformException $e) {
    $e->statusCode;               // 409
    $e->response;                 // ['message' => 'Pull request is not mergeable']
}
```

## Configuration

Published to `config/graft.php`:

```php
return [
    'git_binary' => env('GRAFT_GIT_BINARY', 'git'),
    'timeout'    => env('GRAFT_TIMEOUT', 60),

    'platform' => [
        'default'   => env('GRAFT_PLATFORM', 'github'),
        'providers' => [
            'github' => [
                'token'    => env('GITHUB_TOKEN'),
                'base_url' => env('GITHUB_API_URL', 'https://api.github.com'),
            ],
        ],
    ],
];
```

| Variable | Default | Description |
|---|---|---|
| `GITHUB_TOKEN` | *(required)* | GitHub personal access token |
| `GRAFT_GIT_BINARY` | `git` | Path to the git binary |
| `GRAFT_TIMEOUT` | `60` | Timeout in seconds for git commands |
| `GRAFT_PLATFORM` | `github` | Default platform provider |
| `GITHUB_API_URL` | `https://api.github.com` | GitHub API base URL (for GitHub Enterprise) |

## Data Transfer Objects

All DTOs are readonly classes with typed properties.

**Git:** `Branch`, `Commit`, `Status`, `Remote`, `MergeResult`, `Stash`, `Worktree`, `Blame`

**Platform:** `PullRequest` (active), `Issue` (active), `Comment`, `Review`, `CheckRun`, `CiStatus`, `Repository`

## Contributing

PRs welcome. Run the suite before pushing:

```bash
composer test         # unit + feature
composer test:all     # includes integration (requires real git)
composer phpstan      # level 8
composer lint         # Pint
```

## License

Graft is open-sourced software licensed under the [MIT license](LICENSE).
