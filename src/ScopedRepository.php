<?php

declare(strict_types=1);

namespace Graft;

use Graft\Contracts\GitManager;
use Graft\Contracts\PlatformProvider;
use Graft\Data\Git\Blame;
use Graft\Data\Git\Branch;
use Graft\Data\Git\Commit;
use Graft\Data\Git\MergeResult;
use Graft\Data\Git\Remote;
use Graft\Data\Git\Stash;
use Graft\Data\Git\Status;
use Graft\Data\Git\Worktree;
use Graft\Data\Platform\CheckRun;
use Graft\Data\Platform\CiStatus;
use Graft\Data\Platform\Issue;
use Graft\Data\Platform\PullRequest;
use Graft\Data\Platform\Repository;
use Illuminate\Support\Collection;

class ScopedRepository
{
    protected ?string $detectedRepo = null;

    protected ?PlatformProvider $platform = null;

    public function __construct(
        protected GitManager $manager,
        protected GraftManager $graft,
        protected string $path,
    ) {}

    // ── Git operations (proxy without $repoPath) ────────────

    public function init(bool $bare = false): void
    {
        $this->manager->init($this->path, $bare);
    }

    public function isRepository(): bool
    {
        return $this->manager->isRepository($this->path);
    }

    /**
     * @return Collection<int, Branch>
     */
    public function branches(bool $remote = false): Collection
    {
        return $this->manager->branches($this->path, $remote);
    }

    public function currentBranch(): string
    {
        return $this->manager->currentBranch($this->path);
    }

    public function createBranch(string $name, ?string $startPoint = null): void
    {
        $this->manager->createBranch($this->path, $name, $startPoint);
    }

    public function deleteBranch(string $name, bool $force = false): void
    {
        $this->manager->deleteBranch($this->path, $name, $force);
    }

    public function checkout(string $branch, bool $create = false): void
    {
        $this->manager->checkout($this->path, $branch, $create);
    }

    public function branchExists(string $name): bool
    {
        return $this->manager->branchExists($this->path, $name);
    }

    public function addWorktree(string $path, ?string $branch = null, bool $createBranch = false): Worktree
    {
        return $this->manager->addWorktree($this->path, $path, $branch, $createBranch);
    }

    public function removeWorktree(string $path, bool $force = false): void
    {
        $this->manager->removeWorktree($this->path, $path, $force);
    }

    /**
     * @return Collection<int, Worktree>
     */
    public function listWorktrees(): Collection
    {
        return $this->manager->listWorktrees($this->path);
    }

    public function pruneWorktrees(): void
    {
        $this->manager->pruneWorktrees($this->path);
    }

    public function commit(string $message, bool $allowEmpty = false): Commit
    {
        return $this->manager->commit($this->path, $message, $allowEmpty);
    }

    /**
     * @return Collection<int, Commit>
     */
    public function log(int $limit = 10, ?string $ref = null): Collection
    {
        return $this->manager->log($this->path, $limit, $ref);
    }

    public function show(string $ref = 'HEAD'): Commit
    {
        return $this->manager->show($this->path, $ref);
    }

    public function head(): string
    {
        return $this->manager->head($this->path);
    }

    /**
     * @param  string|list<string>  $paths
     */
    public function add(string|array $paths = '.'): void
    {
        $this->manager->add($this->path, $paths);
    }

    /**
     * @param  string|list<string>  $paths
     */
    public function reset(string|array $paths): void
    {
        $this->manager->reset($this->path, $paths);
    }

    public function status(): Status
    {
        return $this->manager->status($this->path);
    }

    public function diff(bool $staged = false, ?string $path = null): string
    {
        return $this->manager->diff($this->path, $staged, $path);
    }

    public function fetch(?string $remote = null, bool $prune = false): void
    {
        $this->manager->fetch($this->path, $remote, $prune);
    }

    public function pull(?string $remote = null, ?string $branch = null, bool $noRebase = false): void
    {
        $this->manager->pull($this->path, $remote, $branch, $noRebase);
    }

    public function push(?string $remote = null, ?string $branch = null, bool $force = false, bool $setUpstream = false): void
    {
        $this->manager->push($this->path, $remote, $branch, $force, $setUpstream);
    }

    /**
     * @return Collection<int, Remote>
     */
    public function remotes(): Collection
    {
        return $this->manager->remotes($this->path);
    }

    public function addRemote(string $name, string $url): void
    {
        $this->manager->addRemote($this->path, $name, $url);
    }

    public function removeRemote(string $name): void
    {
        $this->manager->removeRemote($this->path, $name);
    }

    public function getConfig(string $key): ?string
    {
        return $this->manager->getConfig($this->path, $key);
    }

    public function setConfig(string $key, string $value): void
    {
        $this->manager->setConfig($this->path, $key, $value);
    }

    /**
     * @return Collection<int, string>
     */
    public function tags(): Collection
    {
        return $this->manager->tags($this->path);
    }

    public function createTag(string $name, ?string $message = null, ?string $ref = null): void
    {
        $this->manager->createTag($this->path, $name, $message, $ref);
    }

    public function deleteTag(string $name): void
    {
        $this->manager->deleteTag($this->path, $name);
    }

    public function stash(?string $message = null, bool $includeUntracked = false): void
    {
        $this->manager->stash($this->path, $message, $includeUntracked);
    }

    public function stashPop(int $index = 0): void
    {
        $this->manager->stashPop($this->path, $index);
    }

    /**
     * @return Collection<int, Stash>
     */
    public function stashList(): Collection
    {
        return $this->manager->stashList($this->path);
    }

    public function stashDrop(int $index = 0): void
    {
        $this->manager->stashDrop($this->path, $index);
    }

    public function merge(string $branch, ?string $message = null, bool $noFf = false): MergeResult
    {
        return $this->manager->merge($this->path, $branch, $message, $noFf);
    }

    public function mergeAbort(): void
    {
        $this->manager->mergeAbort($this->path);
    }

    public function rebase(string $onto): void
    {
        $this->manager->rebase($this->path, $onto);
    }

    public function rebaseAbort(): void
    {
        $this->manager->rebaseAbort($this->path);
    }

    public function rebaseContinue(): void
    {
        $this->manager->rebaseContinue($this->path);
    }

    /**
     * @param  string|list<string>  $commits
     */
    public function cherryPick(string|array $commits): void
    {
        $this->manager->cherryPick($this->path, $commits);
    }

    public function cherryPickAbort(): void
    {
        $this->manager->cherryPickAbort($this->path);
    }

    /**
     * @return Collection<int, Blame>
     */
    public function blame(string $file): Collection
    {
        return $this->manager->blame($this->path, $file);
    }

    public function clean(bool $directories = false, bool $force = true): void
    {
        $this->manager->clean($this->path, $directories, $force);
    }

    // ── Platform operations (auto-detect owner/repo from remote) ─

    public function createPullRequest(string $title, string $body, string $head, string $base, bool $draft = false): PullRequest
    {
        return $this->platform()->createPullRequest($this->detectRepo(), $title, $body, $head, $base, $draft);
    }

    public function getPullRequest(int $number): PullRequest
    {
        return $this->platform()->getPullRequest($this->detectRepo(), $number);
    }

    /**
     * @return Collection<int, PullRequest>
     */
    public function listPullRequests(string $state = 'open'): Collection
    {
        return $this->platform()->listPullRequests($this->detectRepo(), $state);
    }

    /**
     * @param  list<string>  $labels
     */
    public function createIssue(string $title, string $body, array $labels = []): Issue
    {
        return $this->platform()->createIssue($this->detectRepo(), $title, $body, $labels);
    }

    public function getIssue(int $number): Issue
    {
        return $this->platform()->getIssue($this->detectRepo(), $number);
    }

    /**
     * @return Collection<int, Issue>
     */
    public function listIssues(string $state = 'open'): Collection
    {
        return $this->platform()->listIssues($this->detectRepo(), $state);
    }

    public function getCiStatus(string $ref): CiStatus
    {
        return $this->platform()->getCiStatus($this->detectRepo(), $ref);
    }

    /**
     * @return Collection<int, CheckRun>
     */
    public function listCheckRuns(string $ref): Collection
    {
        return $this->platform()->listCheckRuns($this->detectRepo(), $ref);
    }

    public function getRepository(): Repository
    {
        return $this->platform()->getRepository($this->detectRepo());
    }

    // ── Internal ────────────────────────────────────────────

    /**
     * Detect owner/repo from origin remote URL.
     * Supports HTTPS (https://github.com/owner/repo.git) and SSH (git@github.com:owner/repo.git).
     */
    protected function detectRepo(): string
    {
        if ($this->detectedRepo !== null) {
            return $this->detectedRepo;
        }

        $remotes = $this->manager->remotes($this->path);
        $origin = $remotes->firstWhere('name', 'origin');

        if ($origin === null) {
            throw new \RuntimeException('No origin remote found. Cannot detect repository.');
        }

        $url = $origin->fetchUrl;

        // SSH format: git@github.com:owner/repo.git
        if (preg_match('#^git@[^:]+:(.+?)(?:\.git)?$#', $url, $matches)) {
            $this->detectedRepo = $matches[1];

            return $this->detectedRepo;
        }

        // HTTPS format: https://github.com/owner/repo.git
        if (preg_match('#^https?://[^/]+/(.+?)(?:\.git)?$#', $url, $matches)) {
            $this->detectedRepo = $matches[1];

            return $this->detectedRepo;
        }

        throw new \RuntimeException("Cannot parse repository from remote URL: {$url}");
    }

    /**
     * Resolve the platform provider based on remote URL host.
     */
    protected function platform(): PlatformProvider
    {
        if ($this->platform !== null) {
            return $this->platform;
        }

        $this->platform = $this->graft->platformFor($this->path);

        return $this->platform;
    }
}
