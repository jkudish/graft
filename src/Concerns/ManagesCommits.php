<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Carbon\CarbonImmutable;
use Graft\Data\Git\Commit;
use Illuminate\Support\Collection;

trait ManagesCommits
{
    /**
     * Create a commit with the given message.
     */
    public function commit(string $repoPath, string $message, bool $allowEmpty = false): Commit
    {
        $args = ['commit', '-m', $message];

        if ($allowEmpty) {
            $args[] = '--allow-empty';
        }

        $this->run($repoPath, $args);

        return $this->show($repoPath, 'HEAD');
    }

    /**
     * Get commit log history.
     *
     * @return Collection<int, Commit>
     */
    public function log(string $repoPath, int $limit = 10, ?string $ref = null): Collection
    {
        $args = ['log', '--format=%H|%h|%s|%an|%ae|%aI|%P', '-n', (string) $limit];

        if ($ref !== null) {
            $args[] = $ref;
        }

        $output = $this->runAndReturn($repoPath, $args);

        if ($output === '') {
            return collect();
        }

        $lines = explode("\n", $output);

        return collect($lines)->map(function (string $line): Commit {
            return $this->parseCommitLine($line);
        });
    }

    /**
     * Show details of a specific commit.
     */
    public function show(string $repoPath, string $ref = 'HEAD'): Commit
    {
        $output = $this->runAndReturn($repoPath, [
            'show',
            '--format=%H|%h|%s|%an|%ae|%aI|%P',
            '-s',
            $ref,
        ]);

        return $this->parseCommitLine($output);
    }

    /**
     * Get the current HEAD commit hash.
     */
    public function head(string $repoPath): string
    {
        return $this->runAndReturn($repoPath, ['rev-parse', 'HEAD']);
    }

    /**
     * Parse a commit line into a Commit DTO.
     */
    protected function parseCommitLine(string $line): Commit
    {
        $parts = explode('|', $line, 7);
        $hash = $parts[0];
        $shortHash = $parts[1];
        $message = $parts[2];
        $author = $parts[3];
        $email = $parts[4];
        $dateString = $parts[5];
        $parentsString = $parts[6] ?? '';

        $parents = $parentsString !== '' ? explode(' ', $parentsString) : [];

        return new Commit(
            hash: $hash,
            shortHash: $shortHash,
            message: $message,
            author: $author,
            email: $email,
            date: CarbonImmutable::parse($dateString),
            parents: $parents,
        );
    }
}
