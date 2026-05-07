<?php

declare(strict_types=1);

namespace Graft\Facades;

use Graft\Contracts\GitManager;
use Graft\Data\Git\Blame;
use Graft\Data\Git\Branch;
use Graft\Data\Git\Commit;
use Graft\Data\Git\MergeResult;
use Graft\Data\Git\Remote;
use Graft\Data\Git\Stash;
use Graft\Data\Git\Status;
use Graft\Data\Git\Worktree;
use Graft\ScopedRepository;
use Graft\Testing\FakeGitManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ScopedRepository repo(string $path)
 * @method static void init(string $path, bool $bare = false)
 * @method static void clone(string $url, string $path, ?string $branch = null)
 * @method static bool isRepository(string $path)
 * @method static Collection<int, Branch> branches(string $repoPath, bool $remote = false)
 * @method static string currentBranch(string $repoPath)
 * @method static void createBranch(string $repoPath, string $name, ?string $startPoint = null)
 * @method static void deleteBranch(string $repoPath, string $name, bool $force = false)
 * @method static void checkout(string $repoPath, string $branch, bool $create = false)
 * @method static bool branchExists(string $repoPath, string $name)
 * @method static Worktree addWorktree(string $repoPath, string $path, ?string $branch = null, bool $createBranch = false)
 * @method static void removeWorktree(string $repoPath, string $path, bool $force = false)
 * @method static Collection<int, Worktree> listWorktrees(string $repoPath)
 * @method static void pruneWorktrees(string $repoPath)
 * @method static Commit commit(string $repoPath, string $message, bool $allowEmpty = false)
 * @method static Collection<int, Commit> log(string $repoPath, int $limit = 10, ?string $ref = null)
 * @method static Commit show(string $repoPath, string $ref = 'HEAD')
 * @method static string head(string $repoPath)
 * @method static void add(string $repoPath, string|list<string> $paths = '.')
 * @method static void reset(string $repoPath, string|list<string> $paths)
 * @method static Status status(string $repoPath)
 * @method static string diff(string $repoPath, bool $staged = false, ?string $path = null)
 * @method static void fetch(string $repoPath, ?string $remote = null, bool $prune = false)
 * @method static void pull(string $repoPath, ?string $remote = null, ?string $branch = null, bool $noRebase = false)
 * @method static void push(string $repoPath, ?string $remote = null, ?string $branch = null, bool $force = false, bool $setUpstream = false)
 * @method static Collection<int, Remote> remotes(string $repoPath)
 * @method static void addRemote(string $repoPath, string $name, string $url)
 * @method static void removeRemote(string $repoPath, string $name)
 * @method static ?string getConfig(string $repoPath, string $key)
 * @method static void setConfig(string $repoPath, string $key, string $value)
 * @method static Collection<int, string> tags(string $repoPath)
 * @method static void createTag(string $repoPath, string $name, ?string $message = null, ?string $ref = null)
 * @method static void deleteTag(string $repoPath, string $name)
 * @method static void stash(string $repoPath, ?string $message = null, bool $includeUntracked = false)
 * @method static void stashPop(string $repoPath, int $index = 0)
 * @method static Collection<int, Stash> stashList(string $repoPath)
 * @method static void stashDrop(string $repoPath, int $index = 0)
 * @method static MergeResult merge(string $repoPath, string $branch, ?string $message = null, bool $noFf = false)
 * @method static void mergeAbort(string $repoPath)
 * @method static void rebase(string $repoPath, string $onto)
 * @method static void rebaseAbort(string $repoPath)
 * @method static void rebaseContinue(string $repoPath)
 * @method static void cherryPick(string $repoPath, string|list<string> $commits)
 * @method static void cherryPickAbort(string $repoPath)
 * @method static Collection<int, Blame> blame(string $repoPath, string $file)
 * @method static void clean(string $repoPath, bool $directories = false, bool $force = true)
 *
 * @see GitManager
 */
class Git extends Facade
{
    public static function fake(): FakeGitManager
    {
        $fake = new FakeGitManager;
        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return GitManager::class;
    }
}
