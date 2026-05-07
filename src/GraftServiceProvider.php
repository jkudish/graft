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
}
