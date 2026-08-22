<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Carbon\CarbonImmutable;
use Graft\Data\Git\Commit;
use Illuminate\Support\Collection;

trait ManagesCommits
{
    /**
     * NUL-delimited pretty format: hash, short, subject, author, email, date, parents.
     * Subjects may contain `|` and other punctuation; only NUL is a field separator.
     */
    private const COMMIT_PRETTY = '%H%x00%h%x00%s%x00%an%x00%ae%x00%aI%x00%P';

    /**
     * Create a commit with the given message.
     */
    public function commit(string $repoPath, string $message, bool $allowEmpty = false, bool $noVerify = false): Commit
    {
        $args = ['commit', '-m', $message];

        if ($allowEmpty) {
            $args[] = '--allow-empty';
        }

        if ($noVerify) {
            $args[] = '--no-verify';
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
        $args = ['log', '-z', '--format='.self::COMMIT_PRETTY, '-n', (string) $limit];

        if ($ref !== null) {
            $args[] = $ref;
        }

        $output = $this->run($repoPath, $args)->getOutput();

        if ($output === '' || $output === "\0") {
            return collect();
        }

        $fields = explode("\0", $output);

        if ($fields !== [] && $fields[array_key_last($fields)] === '') {
            array_pop($fields);
        }

        $commits = [];

        foreach (array_chunk($fields, 7) as $parts) {
            if (count($parts) < 6) {
                continue;
            }

            $commits[] = $this->parseCommitFields($parts);
        }

        return collect($commits);
    }

    /**
     * Show details of a specific commit.
     */
    public function show(string $repoPath, string $ref = 'HEAD'): Commit
    {
        $output = $this->run($repoPath, [
            'show',
            '-s',
            '--format='.self::COMMIT_PRETTY,
            $ref,
        ])->getOutput();

        return $this->parseCommitFields(explode("\0", $output, 7));
    }

    /**
     * Get the current HEAD commit hash.
     */
    public function head(string $repoPath): string
    {
        return $this->runAndReturn($repoPath, ['rev-parse', 'HEAD']);
    }

    /**
     * Parse NUL-delimited commit fields into a Commit DTO.
     *
     * @param  list<string>  $parts
     */
    protected function parseCommitFields(array $parts): Commit
    {
        $parts = array_pad($parts, 7, '');

        $parentsString = rtrim($parts[6], "\n");
        $parents = $parentsString !== '' ? explode(' ', $parentsString) : [];

        return new Commit(
            hash: $parts[0],
            shortHash: $parts[1],
            message: $parts[2],
            author: $parts[3],
            email: $parts[4],
            date: CarbonImmutable::parse($parts[5]),
            parents: $parents,
        );
    }
}
