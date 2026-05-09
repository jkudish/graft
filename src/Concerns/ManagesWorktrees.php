<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Graft\Data\Git\Worktree;
use Graft\Exceptions\ProcessException;
use Graft\Exceptions\WorktreeException;
use Illuminate\Support\Collection;

trait ManagesWorktrees
{
    abstract protected function installCredentials(string $repoPath): void;

    /**
     * Add a new worktree.
     *
     * When $createBranch is true and the branch already exists, the method
     * skips branch creation and checks out the existing branch instead.
     * This prevents retry loops when a branch was created in a previous attempt.
     *
     * @param  string  $repoPath  Path to the repository
     * @param  string  $path  Path where the worktree will be created
     * @param  string|null  $branch  Branch name to checkout
     * @param  bool  $createBranch  Whether to create a new branch
     *
     * @throws WorktreeException
     */
    public function addWorktree(string $repoPath, string $path, ?string $branch = null, bool $createBranch = false): Worktree
    {
        try {
            $args = ['worktree', 'add'];

            if ($createBranch && $branch) {
                // If the branch already exists, skip the -b flag and just
                // check out the existing branch into the new worktree.
                if ($this->branchExists($repoPath, $branch)) {
                    $createBranch = false;
                } else {
                    $args[] = '-b';
                    $args[] = $branch;
                }
            }

            $args[] = $path;

            if ($branch && ! $createBranch) {
                $args[] = $branch;
            }

            $this->run($repoPath, $args);

            // Worktrees inherit shared config from the main repo, so install
            // the credential helper on the parent rather than the worktree.
            // This also catches the case where the parent was cloned outside
            // Graft (e.g. a manual `git clone` followed by Graft adding a
            // worktree) and so wouldn't already have the helper.
            $this->installCredentials($repoPath);

            // Find and return the worktree we just added
            // Use realpath to handle symlinks (e.g., /tmp -> /private/tmp on macOS)
            $worktrees = $this->listWorktrees($repoPath);
            $realPath = realpath($path);

            return $worktrees->firstWhere('path', $realPath)
                ?? throw new WorktreeException("Failed to find worktree at {$path} after creation", implode(' ', $args), '');
        } catch (ProcessException $e) {
            throw new WorktreeException($e->getMessage(), $e->command, $e->stderr, previous: $e);
        }
    }

    /**
     * Remove a worktree.
     *
     * @param  string  $repoPath  Path to the repository
     * @param  string  $path  Path to the worktree to remove
     * @param  bool  $force  Force removal even if worktree is dirty
     *
     * @throws WorktreeException
     */
    public function removeWorktree(string $repoPath, string $path, bool $force = false): void
    {
        try {
            $args = ['worktree', 'remove'];

            if ($force) {
                $args[] = '--force';
            }

            $args[] = $path;

            $this->run($repoPath, $args);
        } catch (ProcessException $e) {
            throw new WorktreeException($e->getMessage(), $e->command, $e->stderr, previous: $e);
        }
    }

    /**
     * List all worktrees in the repository.
     *
     * @param  string  $repoPath  Path to the repository
     * @return Collection<int, Worktree>
     *
     * @throws WorktreeException
     */
    public function listWorktrees(string $repoPath): Collection
    {
        try {
            $output = $this->runAndReturn($repoPath, ['worktree', 'list', '--porcelain']);

            if (empty($output)) {
                return collect();
            }

            // Split by double newlines to get each worktree block
            /** @var list<string> $blocks */
            $blocks = preg_split('/\n\n+/', trim($output)) ?: [];

            /** @var Collection<int, Worktree> */
            return collect($blocks)->map(function (string $block) {
                $lines = explode("\n", trim($block));
                $data = [
                    'path' => null,
                    'head' => null,
                    'branch' => null,
                    'isBare' => false,
                ];

                foreach ($lines as $line) {
                    if (str_starts_with($line, 'worktree ')) {
                        $data['path'] = substr($line, 9);
                    } elseif (str_starts_with($line, 'HEAD ')) {
                        $data['head'] = substr($line, 5);
                    } elseif (str_starts_with($line, 'branch ')) {
                        $data['branch'] = substr($line, 7);
                    } elseif ($line === 'bare') {
                        $data['isBare'] = true;
                    }
                }

                return new Worktree(
                    path: (string) $data['path'],
                    branch: $data['branch'],
                    head: (string) $data['head'],
                    isBare: $data['isBare'],
                );
            });
        } catch (ProcessException $e) {
            throw new WorktreeException($e->getMessage(), $e->command, $e->stderr, previous: $e);
        }
    }

    /**
     * Prune stale worktree administrative files.
     *
     * @param  string  $repoPath  Path to the repository
     *
     * @throws WorktreeException
     */
    public function pruneWorktrees(string $repoPath): void
    {
        try {
            $this->run($repoPath, ['worktree', 'prune']);
        } catch (ProcessException $e) {
            throw new WorktreeException($e->getMessage(), $e->command, $e->stderr, previous: $e);
        }
    }
}
