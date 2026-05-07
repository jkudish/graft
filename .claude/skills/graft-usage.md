---
description: "Git and GitHub operations via planmode/graft. Activates when working with git commands, branches, PRs, issues, CI checks, or repository automation in Laravel."
triggers:
  - git
  - github
  - branch
  - commit
  - pull request
  - merge
  - rebase
  - push
  - worktree
  - cherry-pick
  - CI status
  - platform provider
---

# Graft — Git & GitHub for Laravel

## Decision: Scoped vs Direct

Use `Git::repo($path)` (scoped) when doing multiple operations on the same repo. It eliminates `$repoPath` repetition and auto-detects `owner/repo` for platform calls.

Use `Git::method($path, ...)` (direct) for one-off operations or when operating across multiple repos.

```php
// Scoped — preferred for orchestration workflows
$repo = Git::repo('/path/to/project');
$repo->checkout('feature/x', create: true);
$repo->add('.');
$repo->commit('Add feature');
$repo->push(setUpstream: true);
$pr = $repo->createPullRequest('Title', 'Body', 'feature/x', 'main');

// Direct — for single operations
$branch = Git::currentBranch('/path/to/repo');
```

## Core Patterns

### Branch + Commit + PR Workflow

```php
$repo = Git::repo($path);
$repo->createBranch('feature/x', 'main');
$repo->checkout('feature/x');
// ... make changes ...
$repo->add('.');
$repo->commit('Implement feature');
$repo->push(setUpstream: true);
$pr = $repo->createPullRequest('Feature X', 'Description', 'feature/x', 'main', draft: true);
$pr->requestReview(['teammate']);
$pr->addLabels(['enhancement']);
```

### Check Status Before Acting

```php
$status = $repo->status();
// Status has: staged[], unstaged[], untracked[], isClean(), hasChanges()
if ($status->hasChanges()) {
    $repo->add('.');
    $repo->commit('Changes');
}
```

### Active Objects on PR and Issue

`PullRequest` and `Issue` returned from platform calls carry action methods — no need to pass `owner/repo` and number again:

```php
$pr = $repo->getPullRequest(42);
$pr->merge(method: 'squash');
$pr->close();
$pr->update(['title' => 'New title']);
$pr->addComment('Ship it');
$pr->getCiStatus();

$issue = $repo->getIssue(10);
$issue->close();
$issue->addComment('Fixed in #42');
$issue->addLabels(['resolved']);
```

### Merge Conflict Handling

```php
use Graft\Exceptions\MergeConflictException;

try {
    $repo->merge('feature/x', noFf: true);
} catch (MergeConflictException $e) {
    $e->conflicts; // list<string> of conflicting file paths
    $repo->mergeAbort();
}
```

### Platform Error Handling

```php
use Graft\Exceptions\PlatformException;

try {
    $pr->merge(method: 'squash');
} catch (PlatformException $e) {
    $e->statusCode; // HTTP status (e.g. 409)
    $e->response;   // array from GitHub API
}
```

## Testing

Both facades have `fake()` methods that swap in recording fakes with semantic assertions.

### Git::fake()

```php
$fake = Git::fake();

// Configure returns before running code
$fake->shouldReturn('currentBranch', 'develop');
$fake->shouldReturn('status', new Status(staged: ['file.php'], unstaged: [], untracked: []));
$fake->shouldThrow('push', new ProcessException('Rejected'));

// Run code under test, then assert
$fake->assertBranchCreated('feature/x');
$fake->assertCheckedOut('feature/x');
$fake->assertCommitted('message substring');
$fake->assertPushed('feature/x');
$fake->assertNothingPushed();
$fake->assertNothingCommitted();
$fake->assertNothingCalled();
$fake->assertCalled('method', fn ($args) => $args[0] === 'value');
$fake->assertCalledTimes('fetch', 2);
```

Semantic assertions: `assertBranchCreated`, `assertCheckedOut`, `assertCommitted`, `assertPushed`, `assertPulled`, `assertFetched`, `assertMerged`, `assertTagCreated`, `assertCloned`, `assertInitialized`, `assertWorktreeAdded`, `assertWorktreeRemoved`, `assertStashed`.

### GitHub::fake()

```php
$fake = GitHub::fake();

$fake->shouldReturn('getPullRequest', $prObject);
$fake->shouldThrow('mergePullRequest', new PlatformException('Conflict', 409));

$fake->assertPrCreated('title');
$fake->assertPrMerged(42);
$fake->assertPrClosed(42);
$fake->assertIssueCreated('title');
$fake->assertIssueClosed(10);
$fake->assertCommentAdded('body substring');
$fake->assertLabelsAdded(['label']);
$fake->assertReviewRequested(['reviewer']);
$fake->assertNothingCalled();
```

## Gotchas

- `Git::add($path)` with no second argument stages everything (`.`). Pass specific paths to be selective.
- `Git::push()` with `setUpstream: true` is needed on first push of a new branch.
- Scoped repo detects `owner/repo` from the origin remote — SSH and HTTPS formats both work. If origin is missing, platform calls will fail.
- `MergeConflictException` is thrown on merge conflicts. Always handle it when calling `merge()`, `rebase()`, or `cherryPick()`.
- All git DTOs are readonly. `PullRequest` and `Issue` are active objects with action methods; other DTOs are plain data.
- `clone` is a valid method name in PHP 8.2+ (it was reserved as a keyword but works as a method on objects/facades).

## Exception Hierarchy

```
GitException (command, stderr)
├── ProcessException
├── BranchException
├── MergeConflictException (conflicts[])
├── WorktreeException
└── TagException
PlatformException (statusCode, response[])
```

## API Quick Lookup

**Git facade** — all methods take `$repoPath` first. On `ScopedRepository`, omit `$repoPath`.

| Area | Methods |
|---|---|
| Repository | `repo`, `init`, `clone`, `isRepository` |
| Branches | `branches`, `currentBranch`, `createBranch`, `deleteBranch`, `checkout`, `branchExists` |
| Commits | `commit`, `log`, `show`, `head` |
| Index | `add`, `reset`, `status`, `diff` |
| Remotes | `fetch`, `pull`, `push`, `remotes`, `addRemote`, `removeRemote` |
| Merge | `merge`, `mergeAbort`, `rebase`, `rebaseAbort`, `rebaseContinue`, `cherryPick`, `cherryPickAbort` |
| Tags | `tags`, `createTag`, `deleteTag` |
| Stash | `stash`, `stashPop`, `stashList`, `stashDrop` |
| Worktrees | `addWorktree`, `removeWorktree`, `listWorktrees`, `pruneWorktrees` |
| Config | `getConfig`, `setConfig` |
| Other | `blame`, `clean` |

**GitHub facade** — all methods take `owner/repo` first. On `ScopedRepository`, omit it.

| Area | Methods |
|---|---|
| Pull Requests | `createPullRequest`, `getPullRequest`, `listPullRequests`, `updatePullRequest`, `mergePullRequest`, `closePullRequest` |
| Reviews | `requestReview`, `listReviews` |
| Comments | `addComment`, `listComments`, `addReviewComment` |
| Issues | `createIssue`, `getIssue`, `listIssues`, `updateIssue` |
| CI/Checks | `getCiStatus`, `listCheckRuns` |
| Labels | `addLabels`, `removeLabel` |
| Repository | `getRepository` |
