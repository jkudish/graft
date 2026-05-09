<?php

declare(strict_types=1);

namespace Graft\Tests\Feature;

use Graft\Auth\GitCredentialHelper;
use Graft\Exceptions\ProcessException;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;
use Illuminate\Support\Facades\Log;
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

    protected function buildProcess(string $repoPath, array $args, ?int $timeout = null, array $extraEnv = []): Process
    {
        $process = parent::buildProcess($repoPath, $args, $timeout, $extraEnv);
        $this->envHistory[] = $process->getEnv();

        return $process;
    }
}

/**
 * Forces installCredentials' inner run() to fail with a ProcessException
 * whose message mirrors what fromProcess() emits — args-with-helper-value
 * concatenated. Used to verify the token doesn't leak into Log calls.
 */
class InstallFailureManager extends ProcessGitManager
{
    public function tryInstall(string $repoPath): void
    {
        $this->installCredentials($repoPath);
    }

    protected function run(string $repoPath, array $args, ?int $timeout = null, array $extraEnv = []): Process
    {
        throw new ProcessException(
            message: 'Git command failed: git '.implode(' ', $args),
            command: 'git '.implode(' ', $args),
            stderr: '',
            code: 1,
        );
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

test('clone passes GIT_CONFIG_* bootstrap env so private clones authenticate', function () {
    // The persisted helper in .git/config only takes effect AFTER clone returns;
    // private clones need credentials DURING clone. We assert the bootstrap env
    // vars are passed to the clone subprocess so git's GIT_CONFIG_COUNT/KEY/VALUE
    // mechanism injects the helper for that one invocation.
    $source = $this->createTestRepositoryWithCommit();
    $helper = new GitCredentialHelper(token: 'ghp_bootstrap_secret');
    $manager = new SpyingProcessGitManager(credentialHelper: $helper);

    $manager->clone($source, $this->repoPath);

    $cloneEnv = collect($manager->envHistory)
        ->filter(fn ($env) => is_array($env) && isset($env['GIT_CONFIG_COUNT']))
        ->first();

    expect($cloneEnv)->not->toBeNull()
        ->and($cloneEnv)
        ->toHaveKey('GIT_CONFIG_COUNT', '1')
        ->toHaveKey('GIT_CONFIG_KEY_0', 'credential.https://github.com.helper')
        ->and($cloneEnv['GIT_CONFIG_VALUE_0'])->toContain('ghp_bootstrap_secret');
});

test('init does NOT pass bootstrap env (no network auth needed)', function () {
    // init creates an empty local repo — no remote, no auth. We don't need
    // (and shouldn't pass) credential bootstrap env there.
    $helper = new GitCredentialHelper(token: 'ghp_init_no_boot');
    $manager = new SpyingProcessGitManager(credentialHelper: $helper);

    $manager->init($this->repoPath);

    foreach ($manager->envHistory as $env) {
        if (is_array($env)) {
            expect($env)->not->toHaveKey('GIT_CONFIG_COUNT');
        }
    }
});

test('credential install failure does not leak the token via logs', function () {
    // ProcessException::fromProcess builds its message from the full git
    // args, which include the credential helper value (and the token
    // literal in baked mode). If installCredentials logged $e->getMessage()
    // on failure, the token would land in production logs. We force that
    // exception path and assert the token never appears in the log payload.
    $token = 'ghp_must_not_leak_'.uniqid();

    $helper = new GitCredentialHelper(token: $token);
    $manager = new InstallFailureManager(credentialHelper: $helper);

    $captured = [];
    Log::shouldReceive('warning')
        ->once()
        ->andReturnUsing(function ($message, $context) use (&$captured) {
            $captured = ['message' => $message, 'context' => $context];
        });

    $manager->tryInstall($this->repoPath);

    $serialized = json_encode($captured);
    expect($serialized)
        ->not->toContain($token)
        ->and($captured['context'] ?? [])
        ->not->toHaveKey('error')
        ->and($captured['context'] ?? [])
        ->toHaveKey('error_class', ProcessException::class);
});
