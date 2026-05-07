<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Graft\Data\Git\Branch;
use Graft\Exceptions\BranchException;
use Illuminate\Support\Collection;

trait ManagesBranches
{
    /**
     * Get all branches in the repository.
     *
     * @return Collection<int, Branch>
     */
    public function branches(string $repoPath, bool $remote = false): Collection
    {
        $args = ['branch', '--format=%(refname:short)|%(HEAD)|%(upstream:short)|%(objectname:short)'];

        if ($remote) {
            $args[] = '--remotes';
        }

        $output = $this->runAndReturn($repoPath, $args);

        if (empty($output)) {
            return collect();
        }

        return collect(explode("\n", $output))
            ->filter()
            ->map(function (string $line) use ($remote) {
                $parts = explode('|', $line);

                return new Branch(
                    name: $parts[0],
                    isCurrent: ($parts[1] ?? '') === '*',
                    isRemote: $remote,
                    upstream: ($parts[2] ?? '') !== '' ? $parts[2] : null,
                    head: ($parts[3] ?? '') !== '' ? $parts[3] : null,
                );
            });
    }

    /**
     * Get the current branch name.
     */
    public function currentBranch(string $repoPath): string
    {
        return $this->runAndReturn($repoPath, ['branch', '--show-current']);
    }

    /**
     * Create a new branch.
     *
     * Idempotent: if the branch already exists, this method returns silently
     * without error. This prevents retry loops in pipeline orchestration when
     * a branch was created in a previous attempt.
     *
     * @throws BranchException
     */
    public function createBranch(string $repoPath, string $name, ?string $startPoint = null): void
    {
        if ($this->branchExists($repoPath, $name)) {
            return;
        }

        $args = ['branch', $name];

        if ($startPoint !== null) {
            $args[] = $startPoint;
        }

        try {
            $this->run($repoPath, $args);
        } catch (\Exception $e) {
            throw new BranchException("Failed to create branch '{$name}': {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Delete a branch.
     *
     * @throws BranchException
     */
    public function deleteBranch(string $repoPath, string $name, bool $force = false): void
    {
        $args = ['branch', $force ? '-D' : '-d', $name];

        try {
            $this->run($repoPath, $args);
        } catch (\Exception $e) {
            throw new BranchException("Failed to delete branch '{$name}': {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Checkout a branch.
     *
     * @throws BranchException
     */
    public function checkout(string $repoPath, string $branch, bool $create = false): void
    {
        $args = ['checkout'];

        if ($create) {
            $args[] = '-b';
        }

        $args[] = $branch;

        try {
            $this->run($repoPath, $args);
        } catch (\Exception $e) {
            throw new BranchException("Failed to checkout branch '{$branch}': {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Check if a branch exists.
     */
    public function branchExists(string $repoPath, string $name): bool
    {
        try {
            $this->run($repoPath, ['show-ref', '--verify', '--quiet', "refs/heads/{$name}"]);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
