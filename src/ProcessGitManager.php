<?php

declare(strict_types=1);

namespace Graft;

use Graft\Concerns\ManagesBranches;
use Graft\Concerns\ManagesCommits;
use Graft\Concerns\ManagesIndex;
use Graft\Concerns\ManagesMerging;
use Graft\Concerns\ManagesRemotes;
use Graft\Concerns\ManagesRepository;
use Graft\Concerns\ManagesStash;
use Graft\Concerns\ManagesTags;
use Graft\Concerns\ManagesWorktrees;
use Graft\Contracts\GitManager;
use Graft\Exceptions\ProcessException;
use Symfony\Component\Process\Process;

class ProcessGitManager implements GitManager
{
    use ManagesBranches;
    use ManagesCommits;
    use ManagesIndex;
    use ManagesMerging;
    use ManagesRemotes;
    use ManagesRepository;
    use ManagesStash;
    use ManagesTags;
    use ManagesWorktrees;

    public function __construct(
        protected string $binary = 'git',
        protected int $timeout = 60,
    ) {}

    public function repo(string $path): ScopedRepository
    {
        return new ScopedRepository(
            manager: $this,
            graft: app(GraftManager::class),
            path: $path,
        );
    }

    /**
     * Run a git command in the given repository path.
     *
     * @param  list<string>  $args
     */
    protected function run(string $repoPath, array $args, ?int $timeout = null): Process
    {
        $process = new Process(
            [$this->binary, ...$args],
            $repoPath,
        );
        $process->setTimeout($timeout ?? $this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw ProcessException::fromProcess($process, $args);
        }

        return $process;
    }

    /**
     * Run a git command and return trimmed stdout.
     *
     * @param  list<string>  $args
     */
    protected function runAndReturn(string $repoPath, array $args, ?int $timeout = null): string
    {
        return trim($this->run($repoPath, $args, $timeout)->getOutput());
    }
}
