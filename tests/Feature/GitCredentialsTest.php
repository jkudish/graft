<?php

declare(strict_types=1);

namespace Graft\Tests\Feature;

use Graft\Auth\GitCredentialHelper;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;
use Symfony\Component\Process\Process;

uses(CreatesTestRepositories::class);

/**
 * Captures the env Symfony Process is constructed with so tests can verify
 * mode=env actually wires the token into subprocess env (not just into
 * .git/config).
 */
class SpyingProcessGitManager extends ProcessGitManager
{
    /** @var list<array<string, string>|null> */
    public array $envHistory = [];

    protected function buildProcess(string $repoPath, array $args, ?int $timeout = null): Process
    {
        $process = parent::buildProcess($repoPath, $args, $timeout);
        $this->envHistory[] = $process->getEnv();

        return $process;
    }
}

beforeEach(function () {
    $this->repoPath = sys_get_temp_dir().'/graft-test-creds-'.uniqid();
    $this->testRepoPaths[] = $this->repoPath;
});

function gitConfigFile(string $repoPath, bool $bare = false): string
{
    return $bare ? "{$repoPath}/config" : "{$repoPath}/.git/config";
}

test('baked mode writes literal token to .git/config after init', function () {
    $helper = new GitCredentialHelper(token: 'ghp_baked_secret');
    $manager = new ProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath);

    $config = file_get_contents(gitConfigFile($this->repoPath));
    expect($config)
        ->toContain('credential "https://github.com"')
        ->toContain('ghp_baked_secret')
        ->toContain('username=x-access-token');
});

test('baked mode writes literal token to .git/config after clone', function () {
    $source = $this->createTestRepositoryWithCommit();
    $helper = new GitCredentialHelper(token: 'ghp_clone_secret');
    $manager = new ProcessGitManager(credentialHelper: $helper);

    $manager->clone($source, $this->repoPath);

    $config = file_get_contents(gitConfigFile($this->repoPath));
    expect($config)->toContain('ghp_clone_secret');
});

test('env mode writes placeholder, never bakes token', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_env_secret',
        mode: GitCredentialHelper::MODE_ENV,
    );
    $manager = new ProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath);

    $config = file_get_contents(gitConfigFile($this->repoPath));
    expect($config)
        ->toContain('${GRAFT_GITHUB_TOKEN}')
        ->not->toContain('ghp_env_secret');
});

test('env mode injects GRAFT_GITHUB_TOKEN into Symfony Process env', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_proc_env',
        mode: GitCredentialHelper::MODE_ENV,
    );
    $manager = new SpyingProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath);

    expect($manager->envHistory)->not->toBeEmpty();
    foreach ($manager->envHistory as $env) {
        expect($env)
            ->toBeArray()
            ->toHaveKey('GRAFT_GITHUB_TOKEN', 'ghp_proc_env');
    }
});

test('baked mode does NOT inject GRAFT_GITHUB_TOKEN into process env', function () {
    $helper = new GitCredentialHelper(token: 'ghp_baked');
    $manager = new SpyingProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath);

    foreach ($manager->envHistory as $env) {
        // env=null means inheriting parent unchanged — also acceptable
        if ($env === null) {
            continue;
        }
        expect($env)->not->toHaveKey('GRAFT_GITHUB_TOKEN');
    }
});

test('disabled helper writes nothing and behavior is unchanged', function () {
    $helper = new GitCredentialHelper(token: 'ghp_disabled', enabled: false);
    $manager = new ProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath);

    $config = file_get_contents(gitConfigFile($this->repoPath));
    expect($config)
        ->not->toContain('ghp_disabled')
        ->not->toContain('credential "https://github.com"');
});

test('null token writes no helper and does not error', function () {
    $helper = new GitCredentialHelper(token: null);
    $manager = new ProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath);

    $config = file_get_contents(gitConfigFile($this->repoPath));
    expect($config)->not->toContain('credential "https://github.com"');
});

test('null credential helper means existing v0.1.x behavior', function () {
    $manager = new ProcessGitManager;

    $manager->init($this->repoPath);

    $config = file_get_contents(gitConfigFile($this->repoPath));
    expect($config)->not->toContain('credential "https://github.com"');
});

test('addWorktree installs credentials on the parent repo', function () {
    // Init parent without graft credentials, simulating a manual clone
    $manager = new ProcessGitManager;
    $manager->init($this->repoPath);
    file_put_contents($this->repoPath.'/file.txt', 'content');
    $manager->setConfig($this->repoPath, 'user.email', 'test@graft.dev');
    $manager->setConfig($this->repoPath, 'user.name', 'Graft Test');
    $manager->add($this->repoPath, '.');
    $manager->commit($this->repoPath, 'initial');

    // Now use a graft-aware manager to add a worktree
    $helper = new GitCredentialHelper(token: 'ghp_worktree_secret');
    $managerWithCreds = new ProcessGitManager(credentialHelper: $helper);

    $worktreePath = sys_get_temp_dir().'/graft-test-wt-'.uniqid();
    $this->testRepoPaths[] = $worktreePath;

    $managerWithCreds->addWorktree(
        $this->repoPath,
        $worktreePath,
        'feature/x',
        createBranch: true,
    );

    // Helper is on the parent, not the worktree (worktrees inherit shared config)
    $parentConfig = file_get_contents(gitConfigFile($this->repoPath));
    expect($parentConfig)->toContain('ghp_worktree_secret');
});

test('init with bare repo writes credential to bare config', function () {
    $helper = new GitCredentialHelper(token: 'ghp_bare_secret');
    $manager = new ProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath, bare: true);

    $config = file_get_contents(gitConfigFile($this->repoPath, bare: true));
    expect($config)->toContain('ghp_bare_secret');
});
