<?php

declare(strict_types=1);

namespace Graft;

use Graft\Ai\Tools\GitBranchesTool;
use Graft\Ai\Tools\GitDiffTool;
use Graft\Ai\Tools\GitHubCreateIssueTool;
use Graft\Ai\Tools\GitHubGetIssueTool;
use Graft\Ai\Tools\GitHubListIssuesTool;
use Graft\Ai\Tools\GitHubListPrsTool;
use Graft\Ai\Tools\GitHubPrReviewTool;
use Graft\Ai\Tools\GitLogTool;
use Graft\Ai\Tools\GitStatusTool;
use Graft\Auth\GitCredentialHelper;
use Graft\Contracts\GitManager;
use Graft\Contracts\PlatformProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class GraftServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/graft.php', 'graft');

        $this->app->singleton(GitManager::class, fn (): ProcessGitManager => new ProcessGitManager(
            binary: config('graft.git_binary', 'git'),
            timeout: config('graft.timeout', 60),
            credentialHelper: $this->makeGitHubCredentialHelper(),
        ));

        $this->app->singleton(GraftManager::class, fn ($app): GraftManager => new GraftManager(
            git: $app->make(GitManager::class),
            config: config('graft'),
        ));

        $this->app->singleton(PlatformProvider::class, fn ($app): PlatformProvider => $app->make(GraftManager::class)->platform());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/graft.php' => config_path('graft.php'),
            ], 'graft-config');
        }

        $this->app->tag([
            GitStatusTool::class,
            GitDiffTool::class,
            GitLogTool::class,
            GitBranchesTool::class,
            GitHubGetIssueTool::class,
            GitHubListIssuesTool::class,
            GitHubListPrsTool::class,
            GitHubCreateIssueTool::class,
            GitHubPrReviewTool::class,
        ], 'ai-tools');

        AboutCommand::add('Graft', fn (): array => [
            'Git Binary' => config('graft.git_binary'),
            'Platform' => config('graft.platform.default'),
        ]);
    }

    /**
     * Build the credential helper for the GitHub provider from config.
     *
     * Returns null when there's no token configured — keeping behavior
     * identical to v0.1.x for apps that never set GITHUB_TOKEN.
     */
    protected function makeGitHubCredentialHelper(): ?GitCredentialHelper
    {
        /** @var array<string, mixed> $github */
        $github = (array) config('graft.platform.providers.github', []);

        $token = isset($github['token']) && is_string($github['token']) ? $github['token'] : null;
        if ($token === null || $token === '') {
            return null;
        }

        /** @var array<string, mixed> $creds */
        $creds = (array) ($github['git_credentials'] ?? []);

        $apiBaseUrl = isset($github['base_url']) && is_string($github['base_url'])
            ? $github['base_url']
            : 'https://api.github.com';

        $host = isset($creds['host']) && is_string($creds['host']) && $creds['host'] !== ''
            ? $creds['host']
            : null;

        return new GitCredentialHelper(
            token: $token,
            enabled: (bool) ($creds['enabled'] ?? true),
            mode: is_string($creds['mode'] ?? null) ? $creds['mode'] : GitCredentialHelper::MODE_BAKED,
            username: is_string($creds['username'] ?? null) && $creds['username'] !== ''
                ? $creds['username']
                : 'x-access-token',
            host: $host,
            apiBaseUrl: $apiBaseUrl,
        );
    }
}
