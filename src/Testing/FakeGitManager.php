<?php

declare(strict_types=1);

namespace Graft\Testing;

use Carbon\CarbonImmutable;
use Graft\Contracts\GitManager;
use Graft\Data\Git\Commit;
use Graft\Data\Git\MergeResult;
use Graft\Data\Git\Status;
use Graft\Data\Git\Worktree;
use Graft\GraftManager;
use Graft\ScopedRepository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

class FakeGitManager implements GitManager
{
    /** @var list<array{method: string, args: list<mixed>}> */
    protected array $calls = [];

    /** @var array<string, mixed> */
    protected array $returnValues = [];

    /** @var array<string, \Throwable> */
    protected array $throwables = [];

    // ── Configurable behavior ───────────────────────────────

    public function shouldReturn(string $method, mixed $value): static
    {
        $this->returnValues[$method] = $value;

        return $this;
    }

    public function shouldThrow(string $method, \Throwable $exception): static
    {
        $this->throwables[$method] = $exception;

        return $this;
    }

    // ── Generic assertions ──────────────────────────────────

    public function assertCalled(string $method, ?\Closure $callback = null): void
    {
        $calls = array_filter($this->calls, fn ($call) => $call['method'] === $method);
        Assert::assertNotEmpty($calls, "Expected [{$method}] to be called, but it was not.");

        if ($callback !== null) {
            $matched = array_filter($calls, fn ($call) => $callback($call['args']));
            Assert::assertNotEmpty($matched, "Expected [{$method}] to be called matching callback, but no matching call found.");
        }
    }

    public function assertNotCalled(string $method): void
    {
        $calls = array_filter($this->calls, fn ($call) => $call['method'] === $method);
        Assert::assertEmpty($calls, "Expected [{$method}] not to be called, but it was called ".count($calls).' time(s).');
    }

    public function assertCalledTimes(string $method, int $times): void
    {
        $count = count(array_filter($this->calls, fn ($call) => $call['method'] === $method));
        Assert::assertEquals($times, $count, "Expected [{$method}] to be called {$times} time(s), but was called {$count} time(s).");
    }

    // ── Semantic assertions ─────────────────────────────────

    public function assertBranchCreated(string $name, ?string $repoPath = null): void
    {
        $this->assertCalled('createBranch', fn ($args) => $args[1] === $name && ($repoPath === null || $args[0] === $repoPath));
    }

    public function assertCheckedOut(string $branch, ?string $repoPath = null): void
    {
        $this->assertCalled('checkout', fn ($args) => $args[1] === $branch && ($repoPath === null || $args[0] === $repoPath));
    }

    public function assertCommitted(?string $messageContains = null, ?string $repoPath = null): void
    {
        $this->assertCalled('commit', function ($args) use ($messageContains, $repoPath) {
            if ($repoPath !== null && $args[0] !== $repoPath) {
                return false;
            }
            if ($messageContains !== null && ! str_contains($args[1], $messageContains)) {
                return false;
            }

            return true;
        });
    }

    public function assertPushed(?string $branch = null, ?string $repoPath = null): void
    {
        $this->assertCalled('push', function ($args) use ($branch, $repoPath) {
            if ($repoPath !== null && $args[0] !== $repoPath) {
                return false;
            }
            if ($branch !== null && ($args[2] ?? null) !== $branch) {
                return false;
            }

            return true;
        });
    }

    public function assertPulled(?string $repoPath = null): void
    {
        $this->assertCalled('pull', fn ($args) => $repoPath === null || $args[0] === $repoPath);
    }

    public function assertWorktreeAdded(string $path, ?string $repoPath = null): void
    {
        $this->assertCalled('addWorktree', fn ($args) => $args[1] === $path && ($repoPath === null || $args[0] === $repoPath));
    }

    public function assertWorktreeRemoved(string $path, ?string $repoPath = null): void
    {
        $this->assertCalled('removeWorktree', fn ($args) => $args[1] === $path && ($repoPath === null || $args[0] === $repoPath));
    }

    public function assertMerged(string $branch, ?string $repoPath = null): void
    {
        $this->assertCalled('merge', fn ($args) => $args[1] === $branch && ($repoPath === null || $args[0] === $repoPath));
    }

    public function assertTagCreated(string $name, ?string $repoPath = null): void
    {
        $this->assertCalled('createTag', fn ($args) => $args[1] === $name && ($repoPath === null || $args[0] === $repoPath));
    }

    public function assertCloned(string $url): void
    {
        $this->assertCalled('clone', fn ($args) => $args[0] === $url);
    }

    public function assertInitialized(string $path): void
    {
        $this->assertCalled('init', fn ($args) => $args[0] === $path);
    }

    public function assertFetched(?string $repoPath = null): void
    {
        $this->assertCalled('fetch', fn ($args) => $repoPath === null || $args[0] === $repoPath);
    }

    public function assertStashed(?string $repoPath = null): void
    {
        $this->assertCalled('stash', fn ($args) => $repoPath === null || $args[0] === $repoPath);
    }

    // ── Nothing assertions ──────────────────────────────────

    public function assertNothingPushed(): void
    {
        $this->assertNotCalled('push');
    }

    public function assertNothingCommitted(): void
    {
        $this->assertNotCalled('commit');
    }

    public function assertNothingCalled(): void
    {
        Assert::assertEmpty($this->calls, 'Expected no methods to be called, but '.count($this->calls).' call(s) were made.');
    }

    // ── Interface implementation ────────────────────────────

    /**
     * @param  list<mixed>  $args
     */
    protected function record(string $method, array $args): mixed
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        if (isset($this->throwables[$method])) {
            throw $this->throwables[$method];
        }

        return $this->returnValues[$method] ?? null;
    }

    public function repo(string $path): ScopedRepository
    {
        $this->record('repo', [$path]);

        return new ScopedRepository($this, app(GraftManager::class), $path);
    }

    public function init(string $path, bool $bare = false): void
    {
        $this->record('init', [$path, $bare]);
    }

    public function clone(string $url, string $path, ?string $branch = null): void
    {
        $this->record('clone', [$url, $path, $branch]);
    }

    public function isRepository(string $path): bool
    {
        return (bool) ($this->record('isRepository', [$path]) ?? true);
    }

    public function branches(string $repoPath, bool $remote = false): Collection
    {
        return $this->record('branches', [$repoPath, $remote]) ?? collect();
    }

    public function currentBranch(string $repoPath): string
    {
        return (string) ($this->record('currentBranch', [$repoPath]) ?? 'main');
    }

    public function createBranch(string $repoPath, string $name, ?string $startPoint = null): void
    {
        $this->record('createBranch', [$repoPath, $name, $startPoint]);
    }

    public function deleteBranch(string $repoPath, string $name, bool $force = false): void
    {
        $this->record('deleteBranch', [$repoPath, $name, $force]);
    }

    public function checkout(string $repoPath, string $branch, bool $create = false): void
    {
        $this->record('checkout', [$repoPath, $branch, $create]);
    }

    public function branchExists(string $repoPath, string $name): bool
    {
        return (bool) ($this->record('branchExists', [$repoPath, $name]) ?? true);
    }

    public function addWorktree(string $repoPath, string $path, ?string $branch = null, bool $createBranch = false): Worktree
    {
        $result = $this->record('addWorktree', [$repoPath, $path, $branch, $createBranch]);

        return $result ?? new Worktree(
            path: $path,
            branch: $branch,
            head: 'abc123',
            isBare: false,
        );
    }

    public function removeWorktree(string $repoPath, string $path, bool $force = false): void
    {
        $this->record('removeWorktree', [$repoPath, $path, $force]);
    }

    public function listWorktrees(string $repoPath): Collection
    {
        return $this->record('listWorktrees', [$repoPath]) ?? collect();
    }

    public function pruneWorktrees(string $repoPath): void
    {
        $this->record('pruneWorktrees', [$repoPath]);
    }

    public function commit(string $repoPath, string $message, bool $allowEmpty = false): Commit
    {
        $result = $this->record('commit', [$repoPath, $message, $allowEmpty]);

        return $result ?? new Commit(
            hash: 'abc123def456',
            shortHash: 'abc123d',
            message: $message,
            author: 'Test Author',
            email: 'test@example.com',
            date: CarbonImmutable::now(),
            parents: [],
        );
    }

    public function log(string $repoPath, int $limit = 10, ?string $ref = null): Collection
    {
        return $this->record('log', [$repoPath, $limit, $ref]) ?? collect();
    }

    public function show(string $repoPath, string $ref = 'HEAD'): Commit
    {
        $result = $this->record('show', [$repoPath, $ref]);

        return $result ?? new Commit(
            hash: 'abc123def456',
            shortHash: 'abc123d',
            message: 'Test commit',
            author: 'Test Author',
            email: 'test@example.com',
            date: CarbonImmutable::now(),
            parents: [],
        );
    }

    public function head(string $repoPath): string
    {
        return (string) ($this->record('head', [$repoPath]) ?? 'abc123def456');
    }

    public function add(string $repoPath, string|array $paths = '.'): void
    {
        $this->record('add', [$repoPath, $paths]);
    }

    public function reset(string $repoPath, string|array $paths): void
    {
        $this->record('reset', [$repoPath, $paths]);
    }

    public function status(string $repoPath): Status
    {
        $result = $this->record('status', [$repoPath]);

        return $result ?? new Status(
            staged: [],
            unstaged: [],
            untracked: [],
        );
    }

    public function diff(string $repoPath, bool $staged = false, ?string $path = null): string
    {
        return (string) ($this->record('diff', [$repoPath, $staged, $path]) ?? '');
    }

    public function fetch(string $repoPath, ?string $remote = null, bool $prune = false): void
    {
        $this->record('fetch', [$repoPath, $remote, $prune]);
    }

    public function pull(string $repoPath, ?string $remote = null, ?string $branch = null, bool $noRebase = false): void
    {
        $this->record('pull', [$repoPath, $remote, $branch, $noRebase]);
    }

    public function push(string $repoPath, ?string $remote = null, ?string $branch = null, bool $force = false, bool $setUpstream = false): void
    {
        $this->record('push', [$repoPath, $remote, $branch, $force, $setUpstream]);
    }

    public function remotes(string $repoPath): Collection
    {
        return $this->record('remotes', [$repoPath]) ?? collect();
    }

    public function addRemote(string $repoPath, string $name, string $url): void
    {
        $this->record('addRemote', [$repoPath, $name, $url]);
    }

    public function removeRemote(string $repoPath, string $name): void
    {
        $this->record('removeRemote', [$repoPath, $name]);
    }

    public function getConfig(string $repoPath, string $key): ?string
    {
        return $this->record('getConfig', [$repoPath, $key]);
    }

    public function setConfig(string $repoPath, string $key, string $value): void
    {
        $this->record('setConfig', [$repoPath, $key, $value]);
    }

    public function tags(string $repoPath): Collection
    {
        return $this->record('tags', [$repoPath]) ?? collect();
    }

    public function createTag(string $repoPath, string $name, ?string $message = null, ?string $ref = null): void
    {
        $this->record('createTag', [$repoPath, $name, $message, $ref]);
    }

    public function deleteTag(string $repoPath, string $name): void
    {
        $this->record('deleteTag', [$repoPath, $name]);
    }

    public function stash(string $repoPath, ?string $message = null, bool $includeUntracked = false): void
    {
        $this->record('stash', [$repoPath, $message, $includeUntracked]);
    }

    public function stashPop(string $repoPath, int $index = 0): void
    {
        $this->record('stashPop', [$repoPath, $index]);
    }

    public function stashList(string $repoPath): Collection
    {
        return $this->record('stashList', [$repoPath]) ?? collect();
    }

    public function stashDrop(string $repoPath, int $index = 0): void
    {
        $this->record('stashDrop', [$repoPath, $index]);
    }

    public function merge(string $repoPath, string $branch, ?string $message = null, bool $noFf = false): MergeResult
    {
        $result = $this->record('merge', [$repoPath, $branch, $message, $noFf]);

        return $result ?? new MergeResult(
            success: true,
            message: null,
            conflicts: [],
        );
    }

    public function mergeAbort(string $repoPath): void
    {
        $this->record('mergeAbort', [$repoPath]);
    }

    public function rebase(string $repoPath, string $onto): void
    {
        $this->record('rebase', [$repoPath, $onto]);
    }

    public function rebaseAbort(string $repoPath): void
    {
        $this->record('rebaseAbort', [$repoPath]);
    }

    public function rebaseContinue(string $repoPath): void
    {
        $this->record('rebaseContinue', [$repoPath]);
    }

    public function cherryPick(string $repoPath, string|array $commits): void
    {
        $this->record('cherryPick', [$repoPath, $commits]);
    }

    public function cherryPickAbort(string $repoPath): void
    {
        $this->record('cherryPickAbort', [$repoPath]);
    }

    public function blame(string $repoPath, string $file): Collection
    {
        return $this->record('blame', [$repoPath, $file]) ?? collect();
    }

    public function clean(string $repoPath, bool $directories = false, bool $force = true): void
    {
        $this->record('clean', [$repoPath, $directories, $force]);
    }
}
