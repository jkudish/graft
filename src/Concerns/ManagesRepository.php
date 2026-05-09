<?php

declare(strict_types=1);

namespace Graft\Concerns;

use Carbon\CarbonImmutable;
use Graft\Data\Git\Blame;
use Illuminate\Support\Collection;

trait ManagesRepository
{
    abstract protected function installCredentials(string $repoPath): void;

    public function init(string $path, bool $bare = false): void
    {
        $args = ['init'];
        if ($bare) {
            $args[] = '--bare';
        }
        $args[] = $path;

        // init runs without a repo path context, use the parent dir
        $this->run(dirname($path), $args);

        $this->installCredentials($path);
    }

    public function clone(string $url, string $path, ?string $branch = null): void
    {
        $args = ['clone'];
        if ($branch !== null) {
            $args[] = '--branch';
            $args[] = $branch;
        }
        $args[] = $url;
        $args[] = $path;

        $this->run(dirname($path), $args, timeout: 300);

        $this->installCredentials($path);
    }

    public function isRepository(string $path): bool
    {
        try {
            $this->run($path, ['rev-parse', '--git-dir']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getConfig(string $repoPath, string $key): ?string
    {
        try {
            return $this->runAndReturn($repoPath, ['config', '--get', $key]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setConfig(string $repoPath, string $key, string $value): void
    {
        $this->run($repoPath, ['config', $key, $value]);
    }

    public function clean(string $repoPath, bool $directories = false, bool $force = true): void
    {
        $args = ['clean'];
        if ($force) {
            $args[] = '-f';
        }
        if ($directories) {
            $args[] = '-d';
        }

        $this->run($repoPath, $args);
    }

    /**
     * @return Collection<int, Blame>
     */
    public function blame(string $repoPath, string $file): Collection
    {
        $output = $this->runAndReturn($repoPath, ['blame', '--porcelain', $file]);

        if ($output === '') {
            return collect();
        }

        return $this->parseBlameOutput($output);
    }

    /**
     * @return Collection<int, Blame>
     */
    protected function parseBlameOutput(string $output): Collection
    {
        $lines = explode("\n", $output);
        $blames = [];
        $currentHash = '';
        $currentAuthor = '';
        $currentDate = null;
        $lineNumber = 0;

        $i = 0;
        while ($i < count($lines)) {
            $line = $lines[$i];

            // Header line: hash origLine finalLine [numLines]
            if (preg_match('/^([0-9a-f]{40}) \d+ (\d+)/', $line, $matches)) {
                $currentHash = $matches[1];
                $lineNumber = (int) $matches[2];
            } elseif (str_starts_with($line, 'author ')) {
                $currentAuthor = substr($line, 7);
            } elseif (str_starts_with($line, 'author-time ')) {
                $timestamp = (int) substr($line, 12);
                $currentDate = CarbonImmutable::createFromTimestamp($timestamp);
            } elseif (str_starts_with($line, "\t")) {
                // Content line — this is the actual source line
                $blames[] = new Blame(
                    lineNumber: $lineNumber,
                    hash: $currentHash,
                    author: $currentAuthor,
                    date: $currentDate ?? CarbonImmutable::now(),
                    content: substr($line, 1),
                );
            }

            $i++;
        }

        return collect($blames);
    }
}
