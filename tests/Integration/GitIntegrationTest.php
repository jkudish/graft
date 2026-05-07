<?php

declare(strict_types=1);

use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class)->group('integration');

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

test('full git lifecycle: init, config, add, commit, branch, checkout, merge, tag, log', function () {
    $path = $this->createTestRepository();

    // Config
    $this->git->setConfig($path, 'user.name', 'Integration Test');
    $this->git->setConfig($path, 'user.email', 'test@graft.dev');
    expect($this->git->getConfig($path, 'user.name'))->toBe('Integration Test');

    // First commit
    $this->createFileInRepo($path, 'README.md', '# Test');
    $this->git->add($path);
    $commit1 = $this->git->commit($path, 'Initial commit');
    expect($commit1->message)->toBe('Initial commit');

    // Branch + checkout
    $this->git->createBranch($path, 'feature');
    $this->git->checkout($path, 'feature');
    expect($this->git->currentBranch($path))->toBe('feature');

    // Commit on feature branch
    $this->createFileInRepo($path, 'feature.txt', 'new feature');
    $this->git->add($path);
    $commit2 = $this->git->commit($path, 'Add feature');
    expect($commit2->message)->toBe('Add feature');

    // Merge back
    $mainBranch = $this->getMainBranch($path);
    $this->git->checkout($path, $mainBranch);
    $result = $this->git->merge($path, 'feature');
    expect($result->success)->toBeTrue();

    // Tag
    $this->git->createTag($path, 'v1.0.0', 'First release');
    expect($this->git->tags($path))->toContain('v1.0.0');

    // Log
    $log = $this->git->log($path, limit: 5);
    expect($log)->toHaveCount(2);
    expect($log->first()->message)->toBe('Add feature');

    // Status clean
    expect($this->git->status($path)->isClean())->toBeTrue();
});

test('worktree lifecycle: add, list, remove, prune', function () {
    $path = $this->createTestRepositoryWithCommit();
    $worktreePath = sys_get_temp_dir().'/graft-wt-'.uniqid();
    $this->testRepoPaths[] = $worktreePath;

    $wt = $this->git->addWorktree($path, $worktreePath, 'wt-branch', createBranch: true);
    expect($wt->branch)->toContain('wt-branch');

    $list = $this->git->listWorktrees($path);
    expect($list)->toHaveCount(2);

    $this->git->removeWorktree($path, $worktreePath);

    $this->git->pruneWorktrees($path);

    $list = $this->git->listWorktrees($path);
    expect($list)->toHaveCount(1);
});

test('stash lifecycle: stash, list, pop', function () {
    $path = $this->createTestRepositoryWithCommit();

    $this->createFileInRepo($path, 'README.md', 'modified content');
    $this->git->stash($path, 'WIP changes');

    expect($this->git->status($path)->isClean())->toBeTrue();

    $stashes = $this->git->stashList($path);
    expect($stashes)->toHaveCount(1);
    expect($stashes->first()->message)->toContain('WIP changes');

    $this->git->stashPop($path);
    expect($this->git->status($path)->hasChanges())->toBeTrue();
});

test('merge conflict detection and abort', function () {
    $path = $this->createTestRepositoryWithCommit();
    $mainBranch = $this->getMainBranch($path);

    // Create conflicting branches
    $this->git->createBranch($path, 'branch-a');
    $this->git->checkout($path, 'branch-a');
    $this->createFileInRepo($path, 'conflict.txt', 'version A');
    $this->git->add($path);
    $this->git->commit($path, 'Branch A changes');

    $this->git->checkout($path, $mainBranch);
    $this->git->createBranch($path, 'branch-b');
    $this->git->checkout($path, 'branch-b');
    $this->createFileInRepo($path, 'conflict.txt', 'version B');
    $this->git->add($path);
    $this->git->commit($path, 'Branch B changes');

    // Merge branch-a into branch-b → conflict
    $result = $this->git->merge($path, 'branch-a');
    expect($result->success)->toBeFalse();
    expect($result->conflicts)->toContain('conflict.txt');

    // Abort
    $this->git->mergeAbort($path);
    expect($this->git->status($path)->isClean())->toBeTrue();
});

test('diff shows staged and unstaged changes', function () {
    $path = $this->createTestRepositoryWithCommit();

    // Unstaged change
    $this->createFileInRepo($path, 'README.md', 'modified');
    $unstagedDiff = $this->git->diff($path);
    expect($unstagedDiff)->toContain('modified');

    // Stage it
    $this->git->add($path);
    $stagedDiff = $this->git->diff($path, staged: true);
    expect($stagedDiff)->toContain('modified');

    // Unstaged diff should now be empty
    expect($this->git->diff($path))->toBe('');
});

test('blame shows file history', function () {
    $path = $this->createTestRepositoryWithCommit();

    $blameData = $this->git->blame($path, 'README.md');
    expect($blameData)->toHaveCount(1);
    expect($blameData->first()->author)->toBe('Graft Test');
    expect($blameData->first()->content)->toBe('# Test Repository');
});

test('branch operations: create, list, delete', function () {
    $path = $this->createTestRepositoryWithCommit();

    $this->git->createBranch($path, 'test-branch');
    expect($this->git->branchExists($path, 'test-branch'))->toBeTrue();

    $branches = $this->git->branches($path);
    expect($branches->pluck('name'))->toContain('test-branch');

    $this->git->deleteBranch($path, 'test-branch');
    expect($this->git->branchExists($path, 'test-branch'))->toBeFalse();
});

test('tag operations: create, list, delete', function () {
    $path = $this->createTestRepositoryWithCommit();

    $this->git->createTag($path, 'v0.1.0');
    expect($this->git->tags($path))->toContain('v0.1.0');

    $this->git->deleteTag($path, 'v0.1.0');
    expect($this->git->tags($path))->not->toContain('v0.1.0');
});

test('reset unstages files', function () {
    $path = $this->createTestRepositoryWithCommit();

    $this->createFileInRepo($path, 'new.txt', 'content');
    $this->git->add($path);

    $status = $this->git->status($path);
    expect($status->staged)->toContain('new.txt');

    $this->git->reset($path, 'new.txt');

    $status = $this->git->status($path);
    expect($status->staged)->not->toContain('new.txt');
    expect($status->untracked)->toContain('new.txt');
});

test('clean removes untracked files', function () {
    $path = $this->createTestRepositoryWithCommit();

    $this->createFileInRepo($path, 'untracked.txt', 'temp');
    $status = $this->git->status($path);
    expect($status->untracked)->toContain('untracked.txt');

    $this->git->clean($path, directories: false, force: true);

    $status = $this->git->status($path);
    expect($status->untracked)->not->toContain('untracked.txt');
});

test('rebase works correctly', function () {
    $path = $this->createTestRepositoryWithCommit();
    $mainBranch = $this->getMainBranch($path);

    // Create feature branch
    $this->git->createBranch($path, 'feature');
    $this->git->checkout($path, 'feature');
    $this->createFileInRepo($path, 'feature.txt', 'feature content');
    $this->git->add($path);
    $this->git->commit($path, 'Add feature');

    // Add commit to main
    $this->git->checkout($path, $mainBranch);
    $this->createFileInRepo($path, 'main.txt', 'main content');
    $this->git->add($path);
    $this->git->commit($path, 'Main changes');

    // Rebase feature onto main
    $this->git->checkout($path, 'feature');
    $this->git->rebase($path, $mainBranch);

    // Verify both files exist
    expect(file_exists($path.'/feature.txt'))->toBeTrue();
    expect(file_exists($path.'/main.txt'))->toBeTrue();
});

test('cherry-pick applies specific commits', function () {
    $path = $this->createTestRepositoryWithCommit();
    $mainBranch = $this->getMainBranch($path);

    // Create feature branch with commits
    $this->git->createBranch($path, 'feature');
    $this->git->checkout($path, 'feature');
    $this->createFileInRepo($path, 'cherry.txt', 'cherry content');
    $this->git->add($path);
    $commit = $this->git->commit($path, 'Cherry-pickable commit');

    // Go back to main and cherry-pick
    $this->git->checkout($path, $mainBranch);
    $this->git->cherryPick($path, $commit->hash);

    // Verify file was cherry-picked
    expect(file_exists($path.'/cherry.txt'))->toBeTrue();
    expect($this->git->log($path, limit: 1)->first()->message)->toBe('Cherry-pickable commit');
});
