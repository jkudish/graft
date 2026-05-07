<?php

use Graft\GraftManager;
use Graft\ProcessGitManager;
use Graft\ScopedRepository;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

// Git proxy tests — verify ScopedRepository correctly delegates to GitManager
test('proxies branch operations', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    $repo->createBranch('feature');
    expect($repo->branchExists('feature'))->toBeTrue();
    expect($repo->currentBranch())->not->toBe('feature');

    $repo->checkout('feature');
    expect($repo->currentBranch())->toBe('feature');
});

test('proxies commit operations', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    $this->createFileInRepo($path, 'new.txt', 'content');
    $repo->add();
    $commit = $repo->commit('Add new file');

    expect($commit->message)->toBe('Add new file');
    expect($repo->status()->isClean())->toBeTrue();
});

test('proxies status and diff', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    expect($repo->status()->isClean())->toBeTrue();

    $this->createFileInRepo($path, 'file.txt', 'content');
    expect($repo->status()->hasChanges())->toBeTrue();
});

// detectRepo tests
test('detects repo from HTTPS remote URL', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    $git->addRemote($path, 'origin', 'https://github.com/jkudish/ops.git');

    // Use reflection to test protected method
    $method = new ReflectionMethod($repo, 'detectRepo');
    expect($method->invoke($repo))->toBe('jkudish/ops');
});

test('detects repo from HTTPS remote URL without .git suffix', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    $git->addRemote($path, 'origin', 'https://github.com/jkudish/ops');

    $method = new ReflectionMethod($repo, 'detectRepo');
    expect($method->invoke($repo))->toBe('jkudish/ops');
});

test('detects repo from SSH remote URL', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    $git->addRemote($path, 'origin', 'git@github.com:jkudish/ops.git');

    $method = new ReflectionMethod($repo, 'detectRepo');
    expect($method->invoke($repo))->toBe('jkudish/ops');
});

test('throws when no origin remote exists', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    $method = new ReflectionMethod($repo, 'detectRepo');
    $method->invoke($repo);
})->throws(RuntimeException::class, 'No origin remote found');

test('caches detected repo', function () {
    $path = $this->createTestRepositoryWithCommit();
    $git = new ProcessGitManager;
    $graft = Mockery::mock(GraftManager::class);
    $repo = new ScopedRepository($git, $graft, $path);

    $git->addRemote($path, 'origin', 'https://github.com/jkudish/ops.git');

    $method = new ReflectionMethod($repo, 'detectRepo');
    $first = $method->invoke($repo);

    // Remove origin - should still return cached value
    $git->removeRemote($path, 'origin');
    $second = $method->invoke($repo);

    expect($first)->toBe($second)->toBe('jkudish/ops');
});
