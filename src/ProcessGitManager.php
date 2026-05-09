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
     * `$extraEnv` is for one-off overrides like injecting `GIT_CONFIG_*` env
     * vars during `git clone` (where the persisted helper isn't yet in
     * `.git/config`). Keys in `$extraEnv` win over the helper's standing env.
     *
     * @param  list<string>  $args
     * @param  array<string, string>  $extraEnv
     */
    protected function buildProcess(string $repoPath, array $args, ?int $timeout = null, array $extraEnv = []): Process
    {
        $env = array_merge(
            $this->credentialHelper?->processEnv() ?? [],
            $extraEnv,
        );

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
     * @param  array<string, string>  $extraEnv
     */
    protected function run(string $repoPath, array $args, ?int $timeout = null, array $extraEnv = []): Process
    {
        $process = $this->buildProcess($repoPath, $args, $timeout, $extraEnv);
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
     * @param  array<string, string>  $extraEnv
     */
    protected function runAndReturn(string $repoPath, array $args, ?int $timeout = null, array $extraEnv = []): string
    {
        return trim($this->run($repoPath, $args, $timeout, $extraEnv)->getOutput());
    }

    /**
     * Env vars that bootstrap credential auth for a git invocation that
     * doesn't yet have the persistent helper installed (notably `git clone`).
     * Empty when no helper is configured, so callers can spread it
     * unconditionally.
     *
     * @return array<string, string>
     */
    protected function credentialBootstrapEnv(): array
    {
        return $this->credentialHelper?->gitConfigEnvForBootstrap() ?? [];
    }

    /**
     * Install Graft's host-scoped credential helper on the repo at $repoPath.
     *
     * Called from init/clone/addWorktree. No-op when the helper is null,
     * disabled, or has no token — so apps without GITHUB_TOKEN see no
     * behavior change.
     *
     * Uses `--replace-all` so a pre-existing key with multiple values
     * doesn't cause `git config` to error with "multiple values".
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
            $this->run($repoPath, [
                'config',
                '--replace-all',
                $this->credentialHelper->configKey(),
                $this->credentialHelper->configValue(),
            ]);
        } catch (Throwable $e) {
            $this->logCredentialInstallFailure($repoPath, $e);
        }
    }

    private function logCredentialInstallFailure(string $repoPath, Throwable $e): void
    {
        // Logging is best-effort: outside Laravel (e.g. raw unit tests with no
        // bound container) the Log facade can't resolve, so we degrade to
        // silent rather than mask the original failure with a logging crash.
        //
        // We deliberately do NOT log $e->getMessage(): ProcessException builds
        // its message from the full git args, which include the credential
        // helper value (and therefore the token literal in baked mode).
        // Logging the exception class is enough to debug install failures
        // without leaking the secret.
        try {
            if (function_exists('app') && app()->bound('log')) {
                Log::warning('Graft: failed to install git credential helper', [
                    'repo' => $repoPath,
                    'error_class' => $e::class,
                ]);
            }
        } catch (Throwable) {
            // Logging itself failed — nothing useful we can do here.
        }
    }
}
