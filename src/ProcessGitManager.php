<?php

declare(strict_types=1);

namespace Graft;

use Graft\Auth\GitCredentialHelper;
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
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

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
        protected ?GitCredentialHelper $credentialHelper = null,
    ) {}

    public function repo(string $path): ScopedRepository
    {
        return new ScopedRepository(
            manager: $this,
            graft: app(GraftManager::class),
            path: $path,
        );
    }

    public function credentialHelper(): ?GitCredentialHelper
    {
        return $this->credentialHelper;
    }

    /**
     * Build the Symfony Process for a git invocation.
     *
     * Extracted so subclasses (and tests) can inspect or override how env is
     * passed. The explicit env array is what makes Graft's git auth survive
     * Laravel's config:cache: when LoadEnvironmentVariables is skipped,
     * $_ENV/$_SERVER are empty and Process can't inherit the token from the
     * parent — so we hand it over directly.
     *
     * @param  list<string>  $args
     */
    protected function buildProcess(string $repoPath, array $args, ?int $timeout = null): Process
    {
        $env = $this->credentialHelper?->processEnv() ?? [];

        $process = new Process(
            command: [$this->binary, ...$args],
            cwd: $repoPath,
            env: $env,
        );
        $process->setTimeout($timeout ?? $this->timeout);

        return $process;
    }

    /**
     * Run a git command in the given repository path.
     *
     * @param  list<string>  $args
     */
    protected function run(string $repoPath, array $args, ?int $timeout = null): Process
    {
        $process = $this->buildProcess($repoPath, $args, $timeout);
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

    /**
     * Install Graft's host-scoped credential helper on the repo at $repoPath.
     *
     * Called from init/clone/addWorktree. No-op when the helper is null,
     * disabled, or has no token — so apps without GITHUB_TOKEN see no
     * behavior change.
     *
     * Failures are logged rather than thrown: a failed credential install
     * shouldn't break an otherwise-successful init/clone, and the next
     * network call will surface auth issues with a clearer error anyway.
     */
    protected function installCredentials(string $repoPath): void
    {
        if ($this->credentialHelper === null || ! $this->credentialHelper->isEnabled()) {
            return;
        }

        try {
            $this->setConfig(
                $repoPath,
                $this->credentialHelper->configKey(),
                $this->credentialHelper->configValue(),
            );
        } catch (Throwable $e) {
            $this->logCredentialInstallFailure($repoPath, $e);
        }
    }

    private function logCredentialInstallFailure(string $repoPath, Throwable $e): void
    {
        // Logging is best-effort: outside Laravel (e.g. raw unit tests with no
        // bound container) the Log facade can't resolve, so we degrade to
        // silent rather than mask the original failure with a logging crash.
        try {
            if (function_exists('app') && app()->bound('log')) {
                Log::warning('Graft: failed to install git credential helper', [
                    'repo' => $repoPath,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (Throwable) {
            // Logging itself failed — nothing useful we can do here.
        }
    }
}
