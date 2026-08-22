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
        $output = $this->run($repoPath, ['status', '-z', '--porcelain=v1'])->getOutput();

        if ($output === '') {
            return new Status(
                staged: [],
                unstaged: [],
                untracked: []
            );
        }

        $staged = [];
        $unstaged = [];
        $untracked = [];
        $offset = 0;

        while (($record = $this->nextNulTerminated($output, $offset)) !== null) {
            if (strlen($record) < 2) {
                continue;
            }

            $statusCode = substr($record, 0, 2);
            // Porcelain v1 -z is `XY PATH\0`; rename/copy adds `ORIG_PATH\0` after.
            $file = strlen($record) > 3 ? substr($record, 3) : '';

            if (str_contains($statusCode, 'R') || str_contains($statusCode, 'C')) {
                $this->nextNulTerminated($output, $offset);
            }

            if ($statusCode === '??') {
                $untracked[] = $file;

                continue;
            }

            if ($statusCode[0] !== ' ' && $statusCode[0] !== '?') {
                $staged[] = $file;
            }

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
     * Read the next NUL-terminated record from porcelain -z output.
     */
    private function nextNulTerminated(string $output, int &$offset): ?string
    {
        if ($offset >= strlen($output)) {
            return null;
        }

        $nextNul = strpos($output, "\0", $offset);

        if ($nextNul === false) {
            $value = substr($output, $offset);
            $offset = strlen($output);

            return $value === '' ? null : $value;
        }

        $value = substr($output, $offset, $nextNul - $offset);
        $offset = $nextNul + 1;

        return $value;
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
