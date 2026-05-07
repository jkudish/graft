<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Graft\Data\Git\Stash;
use Illuminate\Support\Collection;

trait ManagesStash
{
    public function stash(string $repoPath, ?string $message = null, bool $includeUntracked = false): void
    {
        $args = ['stash', 'push'];

        if ($message !== null) {
            $args[] = '-m';
            $args[] = $message;
        }

        if ($includeUntracked) {
            $args[] = '-u';
        }

        $this->run($repoPath, $args);
    }

    public function stashPop(string $repoPath, int $index = 0): void
    {
        $this->run($repoPath, ['stash', 'pop', "stash@{{$index}}"]);
    }

    /**
     * @return Collection<int, Stash>
     */
    public function stashList(string $repoPath): Collection
    {
        try {
            $output = $this->runAndReturn($repoPath, ['stash', 'list', "--format='%gd|%gs|%H'"]);
        } catch (\Throwable) {
            return collect();
        }

        if ($output === '') {
            return collect();
        }

        return $this->parseStashOutput($output);
    }

    public function stashDrop(string $repoPath, int $index = 0): void
    {
        $this->run($repoPath, ['stash', 'drop', "stash@{{$index}}"]);
    }

    /**
     * @return Collection<int, Stash>
     */
    protected function parseStashOutput(string $output): Collection
    {
        $lines = explode("\n", $output);
        $stashes = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            // Remove surrounding quotes if present
            $line = trim($line, "'\"");

            $parts = explode('|', $line);

            if (count($parts) !== 3) {
                continue;
            }

            [$gd, $gs, $hash] = $parts;

            // Extract index from format like "stash@{0}" -> 0
            if (preg_match('/stash@\{(\d+)\}/', $gd, $matches)) {
                $index = (int) $matches[1];
                $stashes[] = new Stash(
                    index: $index,
                    message: $gs,
                    hash: $hash,
                );
            }
        }

        return collect($stashes);
    }
}
