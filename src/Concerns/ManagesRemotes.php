<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Graft\Data\Git\Remote;
use Illuminate\Support\Collection;

trait ManagesRemotes
{
    /**
     * Fetch updates from a remote repository.
     */
    public function fetch(string $repoPath, ?string $remote = null, bool $prune = false): void
    {
        $args = ['fetch'];

        if ($remote !== null) {
            $args[] = $remote;
        }

        if ($prune) {
            $args[] = '--prune';
        }

        $this->run($repoPath, $args);
    }

    /**
     * Pull updates from a remote repository.
     */
    public function pull(string $repoPath, ?string $remote = null, ?string $branch = null, bool $noRebase = false): void
    {
        $args = ['pull'];

        if ($noRebase) {
            $args[] = '--no-rebase';
        }

        if ($remote !== null) {
            $args[] = $remote;
        }

        if ($branch !== null) {
            $args[] = $branch;
        }

        $this->run($repoPath, $args);
    }

    /**
     * Push updates to a remote repository.
     */
    public function push(string $repoPath, ?string $remote = null, ?string $branch = null, bool $force = false, bool $setUpstream = false): void
    {
        $args = ['push'];

        if ($setUpstream) {
            $args[] = '-u';
        }

        if ($force) {
            $args[] = '--force';
        }

        if ($remote !== null) {
            $args[] = $remote;
        }

        if ($branch !== null) {
            $args[] = $branch;
        }

        $this->run($repoPath, $args);
    }

    /**
     * Get all remotes in the repository.
     *
     * @return Collection<int, Remote>
     */
    public function remotes(string $repoPath): Collection
    {
        $output = $this->runAndReturn($repoPath, ['remote', '-v']);

        if (empty($output)) {
            return collect();
        }

        $remotes = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Format: "origin  https://github.com/user/repo.git (fetch)"
            // Format: "origin  https://github.com/user/repo.git (push)"
            if (! preg_match('/^(\S+)\s+(\S+)\s+\((fetch|push)\)$/', $line, $matches)) {
                continue;
            }

            $name = $matches[1];
            $url = $matches[2];
            $type = $matches[3];

            if (! isset($remotes[$name])) {
                $remotes[$name] = [
                    'name' => $name,
                    'fetch' => null,
                    'push' => null,
                ];
            }

            if ($type === 'fetch') {
                $remotes[$name]['fetch'] = $url;
            } elseif ($type === 'push') {
                $remotes[$name]['push'] = $url;
            }
        }

        return collect($remotes)->map(function (array $remote) {
            return new Remote(
                name: $remote['name'],
                fetchUrl: $remote['fetch'] ?? '',
                pushUrl: $remote['push']
            );
        })->values();
    }

    /**
     * Add a remote to the repository.
     */
    public function addRemote(string $repoPath, string $name, string $url): void
    {
        $this->run($repoPath, ['remote', 'add', $name, $url]);
    }

    /**
     * Remove a remote from the repository.
     */
    public function removeRemote(string $repoPath, string $name): void
    {
        $this->run($repoPath, ['remote', 'remove', $name]);
    }
}
