<?php

declare(strict_types=1);

namespace Graft;

use Graft\Contracts\GitManager;
use Graft\Contracts\PlatformProvider;
use Graft\Platform\GitHubProvider;

class GraftManager
{
    /** @var array<string, PlatformProvider> */
    protected array $providers = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected GitManager $git,
        protected array $config,
    ) {}

    public function git(): GitManager
    {
        return $this->git;
    }

    public function platform(?string $name = null): PlatformProvider
    {
        $name ??= $this->config['platform']['default'] ?? 'github';

        return $this->providers[$name] ??= $this->createProvider($name);
    }

    /**
     * Resolve platform provider for a local repo path based on its remote URL.
     */
    public function platformFor(string $repoPath): PlatformProvider
    {
        $remotes = $this->git->remotes($repoPath);
        $origin = $remotes->firstWhere('name', 'origin');

        if ($origin === null) {
            return $this->platform();
        }

        $host = $this->extractHost($origin->fetchUrl);

        return match (true) {
            str_contains($host, 'github') => $this->platform('github'),
            // str_contains($host, 'gitlab') => $this->platform('gitlab'),
            default => $this->platform(),
        };
    }

    protected function createProvider(string $name): PlatformProvider
    {
        $providerConfig = $this->config['platform']['providers'][$name]
            ?? throw new \RuntimeException("Platform provider [{$name}] is not configured.");

        return match ($name) {
            'github' => new GitHubProvider(
                token: $providerConfig['token'] ?? '',
                baseUrl: $providerConfig['base_url'] ?? 'https://api.github.com',
            ),
            default => throw new \RuntimeException("Unsupported platform provider: {$name}"),
        };
    }

    protected function extractHost(string $url): string
    {
        // SSH: git@github.com:owner/repo.git
        if (preg_match('#^git@([^:]+):#', $url, $matches)) {
            return $matches[1];
        }

        // HTTPS: https://github.com/owner/repo.git
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
