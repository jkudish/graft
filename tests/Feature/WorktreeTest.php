<?php

declare(strict_types=1);

use Graft\Data\Git\Worktree;
use Graft\Exceptions\WorktreeException;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

test('addWorktree creates a worktree at the given path', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();

    $worktree = $this->git->addWorktree($repo, $worktreePath);

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->path)->toBe(realpath($worktreePath))
        ->and($worktree->head)->not->toBeNull()
        ->and(is_dir($worktreePath))->toBeTrue();

    // Cleanup
    $this->git->removeWorktree($repo, $worktreePath, force: true);
    @rmdir($worktreePath);
});

test('addWorktree with createBranch creates a new branch', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();
    $branchName = 'feature/test-'.uniqid();

    $worktree = $this->git->addWorktree($repo, $worktreePath, $branchName, createBranch: true);

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->path)->toBe(realpath($worktreePath))
        ->and($worktree->branch)->toBe("refs/heads/{$branchName}")
        ->and(is_dir($worktreePath))->toBeTrue();

    // Verify branch exists
    $branches = $this->git->branches($repo);
    expect($branches->pluck('name')->contains($branchName))->toBeTrue();

    // Cleanup
    $this->git->removeWorktree($repo, $worktreePath, force: true);
    @rmdir($worktreePath);
});

test('addWorktree with createBranch reuses existing branch', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Create a branch first
    $branchName = 'feature/reuse-'.uniqid();
    $this->git->createBranch($repo, $branchName);

    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();

    // addWorktree with createBranch=true should succeed even though
    // the branch already exists, checking it out into the new worktree
    $worktree = $this->git->addWorktree($repo, $worktreePath, $branchName, createBranch: true);

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->path)->toBe(realpath($worktreePath))
        ->and($worktree->branch)->toBe("refs/heads/{$branchName}")
        ->and(is_dir($worktreePath))->toBeTrue();

    // Verify only one branch was created (no duplicate)
    $branches = $this->git->branches($repo);
    expect($branches->pluck('name')->filter(fn ($name) => str_starts_with($name, 'feature/reuse-')))->toHaveCount(1);

    // Cleanup
    $this->git->removeWorktree($repo, $worktreePath, force: true);
    @rmdir($worktreePath);
});

test('addWorktree with existing branch', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Create a branch first
    $branchName = 'feature/existing-'.uniqid();
    $this->git->createBranch($repo, $branchName);

    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();
    $worktree = $this->git->addWorktree($repo, $worktreePath, $branchName);

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->path)->toBe(realpath($worktreePath))
        ->and($worktree->branch)->toBe("refs/heads/{$branchName}")
        ->and(is_dir($worktreePath))->toBeTrue();

    // Cleanup
    $this->git->removeWorktree($repo, $worktreePath, force: true);
    @rmdir($worktreePath);
});

test('listWorktrees lists all worktrees including main', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $worktreePath1 = sys_get_temp_dir().'/graft-test-worktree-1-'.uniqid();
    $worktreePath2 = sys_get_temp_dir().'/graft-test-worktree-2-'.uniqid();

    // Add two worktrees
    $this->git->addWorktree($repo, $worktreePath1, 'branch1', createBranch: true);
    $this->git->addWorktree($repo, $worktreePath2, 'branch2', createBranch: true);

    $worktrees = $this->git->listWorktrees($repo);

    // Should have 3 worktrees: main + 2 added
    expect($worktrees)->toHaveCount(3)
        ->and($worktrees->pluck('path')->contains(realpath($repo)))->toBeTrue()
        ->and($worktrees->pluck('path')->contains(realpath($worktreePath1)))->toBeTrue()
        ->and($worktrees->pluck('path')->contains(realpath($worktreePath2)))->toBeTrue();

    // Cleanup
    $this->git->removeWorktree($repo, $worktreePath1, force: true);
    $this->git->removeWorktree($repo, $worktreePath2, force: true);
    @rmdir($worktreePath1);
    @rmdir($worktreePath2);
});

test('removeWorktree removes a worktree', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();

    $this->git->addWorktree($repo, $worktreePath, 'test-branch', createBranch: true);

    expect(is_dir($worktreePath))->toBeTrue();

    $this->git->removeWorktree($repo, $worktreePath);

    $worktrees = $this->git->listWorktrees($repo);
    expect($worktrees->pluck('path')->contains($worktreePath))->toBeFalse();

    @rmdir($worktreePath);
});

test('removeWorktree with force flag', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();

    $this->git->addWorktree($repo, $worktreePath, 'test-branch', createBranch: true);

    // Create a dirty worktree by adding a file
    file_put_contents($worktreePath.'/test-file.txt', 'test content');

    // Should succeed with force flag
    $this->git->removeWorktree($repo, $worktreePath, force: true);

    $worktrees = $this->git->listWorktrees($repo);
    expect($worktrees->pluck('path')->contains($worktreePath))->toBeFalse();

    @unlink($worktreePath.'/test-file.txt');
    @rmdir($worktreePath);
});

test('pruneWorktrees cleans up stale entries', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();

    $this->git->addWorktree($repo, $worktreePath, 'test-branch', createBranch: true);

    // Manually delete the worktree directory to make it stale
    $this->removeDirectory($worktreePath);

    // Prune should clean up the stale entry
    $this->git->pruneWorktrees($repo);

    // The worktree should no longer be listed
    $worktrees = $this->git->listWorktrees($repo);
    expect($worktrees->pluck('path')->contains($worktreePath))->toBeFalse();
});

test('listWorktrees parses branch and head correctly', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $worktreePath = sys_get_temp_dir().'/graft-test-worktree-'.uniqid();
    $branchName = 'feature/parse-test-'.uniqid();

    $this->git->addWorktree($repo, $worktreePath, $branchName, createBranch: true);

    $worktrees = $this->git->listWorktrees($repo);
    $worktree = $worktrees->firstWhere('path', realpath($worktreePath));

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->path)->toBe(realpath($worktreePath))
        ->and($worktree->branch)->toBe("refs/heads/{$branchName}")
        ->and($worktree->head)->toMatch('/^[a-f0-9]{40}$/')
        ->and($worktree->isBare)->toBeFalse();

    // Cleanup
    $this->git->removeWorktree($repo, $worktreePath, force: true);
    @rmdir($worktreePath);
});

test('addWorktree throws WorktreeException on failure', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $invalidPath = '/nonexistent/invalid/path/'.uniqid();

    expect(fn () => $this->git->addWorktree($repo, $invalidPath))
        ->toThrow(WorktreeException::class);
});

test('removeWorktree throws WorktreeException when worktree does not exist', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $nonexistentPath = sys_get_temp_dir().'/graft-nonexistent-'.uniqid();

    expect(fn () => $this->git->removeWorktree($repo, $nonexistentPath))
        ->toThrow(WorktreeException::class);
});
