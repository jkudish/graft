<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Graft\Data\Git\MergeResult;
use Graft\Exceptions\ProcessException;

trait ManagesMerging
{
    /**
     * Merge a branch into the current branch.
     */
    public function merge(string $repoPath, string $branch, ?string $message = null, bool $noFf = false): MergeResult
    {
        $args = ['merge', $branch];

        if ($noFf) {
            $args[] = '--no-ff';
        }

        if ($message !== null) {
            $args[] = '-m';
            $args[] = $message;
        }

        $process = $this->runAllowingFailure($repoPath, $args);

        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());
        $combinedOutput = trim($output."\n".$errorOutput);

        if ($process->isSuccessful()) {
            return new MergeResult(success: true, message: $output);
        }

        // Check if it's a merge conflict
        if (str_contains($combinedOutput, 'CONFLICT') || str_contains($combinedOutput, 'Automatic merge failed')) {
            $conflicts = $this->getConflictedFiles($repoPath);

            return new MergeResult(success: false, message: $combinedOutput, conflicts: $conflicts);
        }

        // Other error - throw exception
        throw ProcessException::fromProcess($process, $args);
    }

    /**
     * Abort an in-progress merge.
     */
    public function mergeAbort(string $repoPath): void
    {
        $this->run($repoPath, ['merge', '--abort']);
    }

    /**
     * Rebase the current branch onto another branch.
     */
    public function rebase(string $repoPath, string $onto): void
    {
        $this->run($repoPath, ['rebase', $onto]);
    }

    /**
     * Abort an in-progress rebase.
     */
    public function rebaseAbort(string $repoPath): void
    {
        $this->run($repoPath, ['rebase', '--abort']);
    }

    /**
     * Continue an in-progress rebase after resolving conflicts.
     */
    public function rebaseContinue(string $repoPath): void
    {
        $this->run($repoPath, ['rebase', '--continue']);
    }

    /**
     * Cherry-pick one or more commits.
     *
     * @param  string|list<string>  $commits
     */
    public function cherryPick(string $repoPath, string|array $commits): void
    {
        $args = ['cherry-pick'];

        if (is_array($commits)) {
            $args = [...$args, ...$commits];
        } else {
            $args[] = $commits;
        }

        $this->run($repoPath, $args);
    }

    /**
     * Abort an in-progress cherry-pick.
     */
    public function cherryPickAbort(string $repoPath): void
    {
        $this->run($repoPath, ['cherry-pick', '--abort']);
    }

    /**
     * Get list of conflicted files.
     *
     * @return list<string>
     */
    protected function getConflictedFiles(string $repoPath): array
    {
        try {
            $output = $this->run($repoPath, ['diff', '-z', '--name-only', '--diff-filter=U'])->getOutput();

            return array_values(array_filter(explode("\0", $output), fn (string $file): bool => $file !== ''));
        } catch (ProcessException) {
            // If diff fails, return empty array (no conflicts found)
            return [];
        }
    }
}
