<?php

declare(strict_types=1);

use Graft\Exceptions\BranchException;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

it('lists branches', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $branches = $this->git->branches($repo);

    expect($branches)->toHaveCount(1);
    expect($branches->first()->name)->toBeIn(['main', 'master']);
    expect($branches->first()->isCurrent)->toBeTrue();
    expect($branches->first()->isRemote)->toBeFalse();
});

it('returns current branch', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $current = $this->git->currentBranch($repo);

    expect($current)->toBeIn(['main', 'master']);
});

it('creates a new branch', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $this->git->createBranch($repo, 'feature/test');

    expect($this->git->branchExists($repo, 'feature/test'))->toBeTrue();
    $branches = $this->git->branches($repo);
    expect($branches)->toHaveCount(2);
});

it('creates a branch with start point', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $this->git->createBranch($repo, 'feature/base');
    $this->git->checkout($repo, 'feature/base');
    $this->createFileInRepo($repo, 'feature.txt', 'Feature work');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Feature work']);

    $mainBranch = $this->git->branches($repo)->first()->name;
    $this->git->createBranch($repo, 'feature/from-main', $mainBranch);

    expect($this->git->branchExists($repo, 'feature/from-main'))->toBeTrue();
});

it('switches branch with checkout', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $this->git->createBranch($repo, 'feature/test');

    $this->git->checkout($repo, 'feature/test');

    expect($this->git->currentBranch($repo))->toBe('feature/test');
});

it('creates and switches branch with checkout create flag', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $this->git->checkout($repo, 'feature/new', create: true);

    expect($this->git->currentBranch($repo))->toBe('feature/new');
    expect($this->git->branchExists($repo, 'feature/new'))->toBeTrue();
});

it('deletes a branch', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $this->git->createBranch($repo, 'feature/test');

    $this->git->deleteBranch($repo, 'feature/test');

    expect($this->git->branchExists($repo, 'feature/test'))->toBeFalse();
});

it('force deletes an unmerged branch', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $this->git->checkout($repo, 'feature/unmerged', create: true);
    $this->createFileInRepo($repo, 'unmerged.txt', 'Unmerged work');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Unmerged work']);

    $mainBranch = $this->git->branches($repo)->firstWhere('isCurrent', false)->name;
    $this->git->checkout($repo, $mainBranch);

    $this->git->deleteBranch($repo, 'feature/unmerged', force: true);

    expect($this->git->branchExists($repo, 'feature/unmerged'))->toBeFalse();
});

it('checks if branch exists correctly', function () {
    $repo = $this->createTestRepositoryWithCommit();

    expect($this->git->branchExists($repo, 'nonexistent'))->toBeFalse();

    $this->git->createBranch($repo, 'feature/exists');

    expect($this->git->branchExists($repo, 'feature/exists'))->toBeTrue();
});

it('is idempotent when creating a duplicate branch', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $this->git->createBranch($repo, 'feature/test');

    // Creating the same branch again should not throw
    $this->git->createBranch($repo, 'feature/test');

    expect($this->git->branchExists($repo, 'feature/test'))->toBeTrue();
    $branches = $this->git->branches($repo);
    expect($branches)->toHaveCount(2);
});

it('throws exception on actual creation failure', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Use an invalid branch name that git will reject
    $this->git->createBranch($repo, '..invalid-branch-name');
})->throws(BranchException::class);
