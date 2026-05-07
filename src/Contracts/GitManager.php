<?php

declare(strict_types=1);

namespace Graft\Contracts;

use Graft\Data\Git\Blame;
use Graft\Data\Git\Branch;
use Graft\Data\Git\Commit;
use Graft\Data\Git\MergeResult;
use Graft\Data\Git\Remote;
use Graft\Data\Git\Stash;
use Graft\Data\Git\Status;
use Graft\Data\Git\Worktree;
use Graft\ScopedRepository;
use Illuminate\Support\Collection;

interface GitManager
{
    // Repository
    public function repo(string $path): ScopedRepository;

    public function init(string $path, bool $bare = false): void;

    public function clone(string $url, string $path, ?string $branch = null): void;

    public function isRepository(string $path): bool;

    // Branches
    /**
     * @return Collection<int, Branch>
     */
    public function branches(string $repoPath, bool $remote = false): Collection;

    public function currentBranch(string $repoPath): string;

    public function createBranch(string $repoPath, string $name, ?string $startPoint = null): void;

    public function deleteBranch(string $repoPath, string $name, bool $force = false): void;

    public function checkout(string $repoPath, string $branch, bool $create = false): void;

    public function branchExists(string $repoPath, string $name): bool;

    // Worktrees
    public function addWorktree(string $repoPath, string $path, ?string $branch = null, bool $createBranch = false): Worktree;

    public function removeWorktree(string $repoPath, string $path, bool $force = false): void;

    /**
     * @return Collection<int, Worktree>
     */
    public function listWorktrees(string $repoPath): Collection;

    public function pruneWorktrees(string $repoPath): void;

    // Commits
    public function commit(string $repoPath, string $message, bool $allowEmpty = false): Commit;

    /**
     * @return Collection<int, Commit>
     */
    public function log(string $repoPath, int $limit = 10, ?string $ref = null): Collection;

    public function show(string $repoPath, string $ref = 'HEAD'): Commit;

    public function head(string $repoPath): string;

    // Index
    /**
     * @param  string|list<string>  $paths
     */
    public function add(string $repoPath, string|array $paths = '.'): void;

    /**
     * @param  string|list<string>  $paths
     */
    public function reset(string $repoPath, string|array $paths): void;

    public function status(string $repoPath): Status;

    public function diff(string $repoPath, bool $staged = false, ?string $path = null): string;

    // Remotes
    public function fetch(string $repoPath, ?string $remote = null, bool $prune = false): void;

    public function pull(string $repoPath, ?string $remote = null, ?string $branch = null, bool $noRebase = false): void;

    public function push(string $repoPath, ?string $remote = null, ?string $branch = null, bool $force = false, bool $setUpstream = false): void;

    /**
     * @return Collection<int, Remote>
     */
    public function remotes(string $repoPath): Collection;

    public function addRemote(string $repoPath, string $name, string $url): void;

    public function removeRemote(string $repoPath, string $name): void;

    // Config
    public function getConfig(string $repoPath, string $key): ?string;

    public function setConfig(string $repoPath, string $key, string $value): void;

    // Tags
    /**
     * @return Collection<int, string>
     */
    public function tags(string $repoPath): Collection;

    public function createTag(string $repoPath, string $name, ?string $message = null, ?string $ref = null): void;

    public function deleteTag(string $repoPath, string $name): void;

    // Stash
    public function stash(string $repoPath, ?string $message = null, bool $includeUntracked = false): void;

    public function stashPop(string $repoPath, int $index = 0): void;

    /**
     * @return Collection<int, Stash>
     */
    public function stashList(string $repoPath): Collection;

    public function stashDrop(string $repoPath, int $index = 0): void;

    // Merge
    public function merge(string $repoPath, string $branch, ?string $message = null, bool $noFf = false): MergeResult;

    public function mergeAbort(string $repoPath): void;

    // Rebase
    public function rebase(string $repoPath, string $onto): void;

    public function rebaseAbort(string $repoPath): void;

    public function rebaseContinue(string $repoPath): void;

    // Cherry-pick
    /**
     * @param  string|list<string>  $commits
     */
    public function cherryPick(string $repoPath, string|array $commits): void;

    public function cherryPickAbort(string $repoPath): void;

    // Blame
    /**
     * @return Collection<int, Blame>
     */
    public function blame(string $repoPath, string $file): Collection;

    // Clean
    public function clean(string $repoPath, bool $directories = false, bool $force = true): void;
}
