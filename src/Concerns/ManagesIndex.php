<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Graft\Data\Git\Status;

trait ManagesIndex
{
    /**
     * Stage files to the index.
     *
     * @param  string|list<string>  $paths
     */
    public function add(string $repoPath, string|array $paths = '.'): void
    {
        $args = ['add'];

        if (is_array($paths)) {
            $args = [...$args, ...$paths];
        } else {
            $args[] = $paths;
        }

        $this->run($repoPath, $args);
    }

    /**
     * Unstage files from the index.
     *
     * @param  string|list<string>  $paths
     */
    public function reset(string $repoPath, string|array $paths): void
    {
        $args = ['reset', '--'];

        if (is_array($paths)) {
            $args = [...$args, ...$paths];
        } else {
            $args[] = $paths;
        }

        $this->run($repoPath, $args);
    }

    /**
     * Get the current status of the repository.
     */
    public function status(string $repoPath): Status
    {
        $output = rtrim($this->run($repoPath, ['status', '--porcelain'])->getOutput());

        if (empty($output)) {
            return new Status(
                staged: [],
                unstaged: [],
                untracked: []
            );
        }

        $staged = [];
        $unstaged = [];
        $untracked = [];

        foreach (explode("\n", $output) as $line) {
            if (empty($line)) {
                continue;
            }

            $statusCode = substr($line, 0, 2);
            $file = (string) substr($line, 3);

            // Handle renames: "R  old -> new"
            if (str_starts_with($statusCode, 'R')) {
                $file = (string) preg_replace('/.*\s+->\s+/', '', $file);
            }

            // Untracked files
            if ($statusCode === '??') {
                $untracked[] = $file;

                continue;
            }

            // Staged changes (first character)
            if ($statusCode[0] !== ' ' && $statusCode[0] !== '?') {
                $staged[] = $file;
            }

            // Unstaged changes (second character)
            if ($statusCode[1] !== ' ') {
                $unstaged[] = $file;
            }
        }

        return new Status(
            staged: $staged,
            unstaged: $unstaged,
            untracked: $untracked
        );
    }

    /**
     * Get the diff output for the repository.
     */
    public function diff(string $repoPath, bool $staged = false, ?string $path = null): string
    {
        $args = ['diff'];

        if ($staged) {
            $args[] = '--staged';
        }

        if ($path !== null) {
            $args[] = '--';
            $args[] = $path;
        }

        return $this->runAndReturn($repoPath, $args);
    }
}
