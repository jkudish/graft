# Graft

Git manager and platform provider for Laravel.

Graft wraps git CLI operations and GitHub API calls behind clean facades, typed DTOs, and first-class test fakes. Use `Git::` for local repository work, `GitHub::` for platform operations, or `Git::repo($path)` to scope both to a single repository.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Git binary available on `PATH`

## Installation

```bash
composer require planmode/graft
```

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=graft-config
```

Set your GitHub token in `.env`:

```dotenv
GITHUB_TOKEN=ghp_your_token_here
```

## Quick Start

```php
use Graft\Facades\Git;
use Graft\Facades\GitHub;

// Scope everything to a single repository
$repo = Git::repo('/path/to/project');

// Branch, commit, push
$repo->checkout('feature/new-thing', create: true);
$repo->add('.');
$repo->commit('Add new feature');
$repo->push(setUpstream: true);

// Open a PR and request review
$pr = $repo->createPullRequest(
    title: 'Add new feature',
    body: 'Description of changes',
    head: 'feature/new-thing',
    base: 'main',
);
$pr->requestReview(['teammate']);
$pr->addLabels(['enhancement']);
```

## Git Operations

The `Git` facade proxies all methods on `GitManager`. Every method takes `$repoPath` as its first argument.

### Branches

```php
use Graft\Facades\Git;

$branches = Git::branches('/path/to/repo');           // Collection<Branch>
$current  = Git::currentBranch('/path/to/repo');       // "main"
$exists   = Git::branchExists('/path/to/repo', 'dev');

Git::createBranch('/path/to/repo', 'feature/x', 'main');
Git::checkout('/path/to/repo', 'feature/x');
Git::deleteBranch('/path/to/repo', 'feature/x', force: true);
```

### Commits and Index

```php
Git::add('/path/to/repo', ['src/File.php', 'tests/FileTest.php']);
Git::add('/path/to/repo');  // stages everything

$commit = Git::commit('/path/to/repo', 'Fix the thing');
// => Commit { hash, shortHash, message, author, email, date, parents }

$log    = Git::log('/path/to/repo', limit: 5);        // Collection<Commit>
$head   = Git::show('/path/to/repo');                  // Commit at HEAD
$status = Git::status('/path/to/repo');                // Status { staged, unstaged, untracked }
$diff   = Git::diff('/path/to/repo', staged: true);   // string
```

### Remotes and Syncing

```php
Git::fetch('/path/to/repo', prune: true);
Git::pull('/path/to/repo', 'origin', 'main');
Git::push('/path/to/repo', 'origin', 'main', force: false, setUpstream: true);

$remotes = Git::remotes('/path/to/repo');  // Collection<Remote>
Git::addRemote('/path/to/repo', 'upstream', 'https://github.com/org/repo.git');
Git::removeRemote('/path/to/repo', 'upstream');
```

### Merge, Rebase, Cherry-pick

```php
$result = Git::merge('/path/to/repo', 'feature/x', noFf: true);
// => MergeResult { success: true, message: null, conflicts: [] }

Git::rebase('/path/to/repo', 'main');
Git::cherryPick('/path/to/repo', ['abc123', 'def456']);

// Abort operations
Git::mergeAbort('/path/to/repo');
Git::rebaseAbort('/path/to/repo');
Git::cherryPickAbort('/path/to/repo');
```

### Tags

```php
$tags = Git::tags('/path/to/repo');  // Collection<string>
Git::createTag('/path/to/repo', 'v1.0.0', message: 'Release 1.0');
Git::deleteTag('/path/to/repo', 'v1.0.0');
```

### Stash

```php
Git::stash('/path/to/repo', message: 'WIP', includeUntracked: true);
Git::stashPop('/path/to/repo');
$stashes = Git::stashList('/path/to/repo');  // Collection<Stash>
Git::stashDrop('/path/to/repo', index: 0);
```

### Worktrees

```php
$wt = Git::addWorktree('/path/to/repo', '/tmp/worktree', 'feature/x', createBranch: true);
// => Worktree { path, branch, head, isBare }

$worktrees = Git::listWorktrees('/path/to/repo');
Git::removeWorktree('/path/to/repo', '/tmp/worktree', force: true);
Git::pruneWorktrees('/path/to/repo');
```

### Repository and Config

```php
Git::init('/path/to/new-repo', bare: false);
Git::clone('https://github.com/org/repo.git', '/path/to/dest', branch: 'main');
Git::isRepository('/some/path');  // bool

Git::getConfig('/path/to/repo', 'user.name');            // ?string
Git::setConfig('/path/to/repo', 'user.name', 'Graft');

$blame = Git::blame('/path/to/repo', 'src/File.php');    // Collection<Blame>
Git::clean('/path/to/repo', directories: true);
```

## Scoped Repository

`Git::repo($path)` returns a `ScopedRepository` that binds both git and platform operations to a single path. No more passing `$repoPath` everywhere.

```php
$repo = Git::repo('/path/to/project');

// Git operations without $repoPath
$repo->currentBranch();
$repo->checkout('feature/x', create: true);
$repo->add('.');
$repo->commit('Changes');
$repo->push(setUpstream: true);

// Platform operations auto-detect owner/repo from the origin remote
$pr = $repo->createPullRequest(
    title: 'Feature X',
    body: 'Description',
    head: 'feature/x',
    base: 'main',
);

$issues = $repo->listIssues();
$ci     = $repo->getCiStatus('feature/x');
```

The scoped repository detects `owner/repo` from the origin remote URL (supports both HTTPS and SSH formats) and resolves the correct platform provider automatically.

## Platform Operations (GitHub)

The `GitHub` facade works with any repository by passing `owner/repo` directly.

### Pull Requests

```php
use Graft\Facades\GitHub;

$pr = GitHub::createPullRequest('owner/repo', 'Title', 'Body', 'feature', 'main', draft: true);
$pr = GitHub::getPullRequest('owner/repo', 42);
$prs = GitHub::listPullRequests('owner/repo', state: 'open');

GitHub::updatePullRequest('owner/repo', 42, ['title' => 'New title']);
GitHub::mergePullRequest('owner/repo', 42, method: 'squash');
GitHub::closePullRequest('owner/repo', 42);
```

### Issues

```php
$issue = GitHub::createIssue('owner/repo', 'Bug report', 'Details', labels: ['bug']);
$issue = GitHub::getIssue('owner/repo', 10);
$issues = GitHub::listIssues('owner/repo', state: 'open');
GitHub::updateIssue('owner/repo', 10, ['state' => 'closed']);
```

### Reviews and Comments

```php
GitHub::requestReview('owner/repo', 42, ['reviewer1', 'reviewer2']);
$reviews = GitHub::listReviews('owner/repo', 42);  // Collection<Review>

$comment = GitHub::addComment('owner/repo', 42, 'Looks good!');
$comments = GitHub::listComments('owner/repo', 42);
GitHub::addReviewComment('owner/repo', 42, 'Nit: typo', 'abc123', 'src/File.php', 15);
```

### CI and Labels

```php
$ci = GitHub::getCiStatus('owner/repo', 'abc123');
// => CiStatus { state: "success", checkRuns: Collection<CheckRun> }

$checks = GitHub::listCheckRuns('owner/repo', 'abc123');

GitHub::addLabels('owner/repo', 42, ['ready-for-review']);
GitHub::removeLabel('owner/repo', 42, 'wip');
```

### Repository Info

```php
$repo = GitHub::getRepository('owner/repo');
// => Repository { name, fullName, description, defaultBranch, private, url }
```

## Active Objects

`PullRequest` and `Issue` DTOs returned from the platform provider carry a reference to the provider, enabling action methods directly on the object.

### PullRequest

```php
$pr = GitHub::getPullRequest('owner/repo', 42);

$pr->merge(method: 'squash');
$pr->close();
$pr->update(['title' => 'Updated title']);
$pr->requestReview(['teammate']);
$pr->listReviews();
$pr->addComment('Ship it!');
$pr->listComments();
$pr->addReviewComment('Fix this', 'abc123', 'src/File.php', 10);
$pr->getCiStatus();
$pr->addLabels(['approved']);
$pr->removeLabel('wip');
```

### Issue

```php
$issue = GitHub::getIssue('owner/repo', 10);

$issue->close();
$issue->update(['title' => 'Updated title']);
$issue->addComment('Fixed in PR #42');
$issue->listComments();
$issue->addLabels(['resolved']);
$issue->removeLabel('bug');
```

## Testing

Both facades have `fake()` methods that swap the real implementation with a recording fake. The fakes record all calls and provide semantic assertions.

### Git::fake()

```php
use Graft\Facades\Git;

it('creates a feature branch and pushes', function () {
    $fake = Git::fake();

    // Run your code that uses Git::...

    $fake->assertBranchCreated('feature/x');
    $fake->assertCheckedOut('feature/x');
    $fake->assertCommitted('Add feature');
    $fake->assertPushed('feature/x');
});
```

#### Available Git Assertions

```php
$fake->assertCalled('methodName');               // Generic: method was called
$fake->assertCalled('commit', fn ($args) => ...); // With argument check
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

// Nothing assertions
$fake->assertNothingPushed();
$fake->assertNothingCommitted();
$fake->assertNothingCalled();
```

#### Configuring Return Values and Exceptions

```php
$fake = Git::fake();
$fake->shouldReturn('currentBranch', 'develop');
$fake->shouldReturn('status', new Status(staged: ['file.php'], unstaged: [], untracked: []));

$fake->shouldThrow('push', new ProcessException('Remote rejected'));
```

### GitHub::fake()

```php
use Graft\Facades\GitHub;

it('creates a PR and requests review', function () {
    $fake = GitHub::fake();

    // Run your code that uses GitHub::...

    $fake->assertPrCreated('Add feature');
    $fake->assertReviewRequested(['teammate']);
    $fake->assertLabelsAdded(['enhancement']);
});
```

#### Available GitHub Assertions

```php
$fake->assertCalled('methodName');
$fake->assertNotCalled('methodName');
$fake->assertCalledTimes('methodName', 2);

// Semantic
$fake->assertPrCreated('title');
$fake->assertPrMerged(42);
$fake->assertPrClosed(42);
$fake->assertIssueCreated('title');
$fake->assertIssueClosed(10);
$fake->assertCommentAdded('body substring');
$fake->assertLabelsAdded(['label1', 'label2']);
$fake->assertReviewRequested(['reviewer1']);
$fake->assertNothingCalled();
```

## Error Handling

All exceptions extend from two base classes:

```
RuntimeException
├── GitException                  # Base for all git errors (command, stderr)
│   ├── ProcessException          # Git process failed
│   ├── BranchException           # Branch operation failed
│   ├── MergeConflictException    # Merge conflict (conflicts list)
│   ├── WorktreeException         # Worktree operation failed
│   └── TagException              # Tag operation failed
└── PlatformException             # GitHub API error (statusCode, response)
```

```php
use Graft\Exceptions\MergeConflictException;
use Graft\Exceptions\PlatformException;

try {
    Git::merge($path, 'feature/x');
} catch (MergeConflictException $e) {
    $conflictingFiles = $e->conflicts;  // list<string>
    Git::mergeAbort($path);
}

try {
    GitHub::mergePullRequest('owner/repo', 42);
} catch (PlatformException $e) {
    $e->statusCode;  // 409
    $e->response;    // ['message' => 'Pull request is not mergeable']
}
```

## Configuration

Published to `config/graft.php`:

```php
return [
    // Path to the git binary
    'git_binary' => env('GRAFT_GIT_BINARY', 'git'),

    // Default timeout in seconds for git commands
    'timeout' => env('GRAFT_TIMEOUT', 60),

    'platform' => [
        'default' => env('GRAFT_PLATFORM', 'github'),

        'providers' => [
            'github' => [
                'token' => env('GITHUB_TOKEN'),
                'base_url' => env('GITHUB_API_URL', 'https://api.github.com'),
            ],
        ],
    ],
];
```

| Variable | Default | Description |
|---|---|---|
| `GITHUB_TOKEN` | *(required)* | GitHub personal access token |
| `GRAFT_GIT_BINARY` | `git` | Path to git binary |
| `GRAFT_TIMEOUT` | `60` | Timeout in seconds for git commands |
| `GRAFT_PLATFORM` | `github` | Default platform provider |
| `GITHUB_API_URL` | `https://api.github.com` | GitHub API base URL (for GitHub Enterprise) |

## Data Transfer Objects

All DTOs are readonly classes with typed properties.

**Git:** `Branch`, `Commit`, `Status`, `Remote`, `MergeResult`, `Stash`, `Worktree`, `Blame`

**Platform:** `PullRequest` (active), `Issue` (active), `Comment`, `Review`, `CheckRun`, `CiStatus`, `Repository`

## License

Graft is open-sourced software licensed under the [MIT license](LICENSE).
