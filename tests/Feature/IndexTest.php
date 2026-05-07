<?php

declare(strict_types=1);

use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

it('stages a file', function () {
    $repo = $this->createTestRepository();

    // Create a new file
    file_put_contents($repo.'/newfile.txt', 'content');

    // Add the file
    $this->git->add($repo, 'newfile.txt');

    // Verify it's staged
    $status = $this->git->status($repo);
    expect($status->staged)->toContain('newfile.txt')
        ->and($status->unstaged)->toBeEmpty()
        ->and($status->untracked)->toBeEmpty();
});

it('stages multiple files with array', function () {
    $repo = $this->createTestRepository();

    // Create multiple files
    file_put_contents($repo.'/file1.txt', 'content1');
    file_put_contents($repo.'/file2.txt', 'content2');

    // Add multiple files
    $this->git->add($repo, ['file1.txt', 'file2.txt']);

    // Verify they're staged
    $status = $this->git->status($repo);
    expect($status->staged)->toContain('file1.txt')
        ->and($status->staged)->toContain('file2.txt');
});

it('stages everything with dot', function () {
    $repo = $this->createTestRepository();

    // Create multiple files
    file_put_contents($repo.'/file1.txt', 'content1');
    file_put_contents($repo.'/file2.txt', 'content2');

    // Add everything
    $this->git->add($repo, '.');

    // Verify all files are staged
    $status = $this->git->status($repo);
    expect($status->staged)->toContain('file1.txt')
        ->and($status->staged)->toContain('file2.txt');
});

it('unstages a file', function () {
    $repo = $this->createTestRepository();

    // Create and stage a file
    file_put_contents($repo.'/file.txt', 'content');
    $this->git->add($repo, 'file.txt');

    // Reset the file
    $this->git->reset($repo, 'file.txt');

    // Verify it's unstaged
    $status = $this->git->status($repo);
    expect($status->staged)->toBeEmpty()
        ->and($status->untracked)->toContain('file.txt');
});

it('returns clean status for clean repo', function () {
    $repo = $this->createTestRepository();

    $status = $this->git->status($repo);

    expect($status->staged)->toBeEmpty()
        ->and($status->unstaged)->toBeEmpty()
        ->and($status->untracked)->toBeEmpty()
        ->and($status->isClean())->toBeTrue()
        ->and($status->hasChanges())->toBeFalse();
});

it('detects staged files', function () {
    $repo = $this->createTestRepository();

    // Create and stage a file
    file_put_contents($repo.'/staged.txt', 'content');
    $this->git->add($repo, 'staged.txt');

    $status = $this->git->status($repo);

    expect($status->staged)->toContain('staged.txt')
        ->and($status->isClean())->toBeFalse()
        ->and($status->hasChanges())->toBeTrue();
});

it('detects unstaged modifications', function () {
    $repo = $this->createTestRepository();

    // Create, commit, then modify a file
    file_put_contents($repo.'/file.txt', 'original');
    $this->git->add($repo, 'file.txt');
    $this->git->commit($repo, 'Initial commit');

    // Modify the file without staging
    file_put_contents($repo.'/file.txt', 'modified');

    $status = $this->git->status($repo);

    expect($status->unstaged)->toContain('file.txt')
        ->and($status->staged)->toBeEmpty()
        ->and($status->isClean())->toBeFalse();
});

it('detects untracked files', function () {
    $repo = $this->createTestRepository();

    // Create a new file without staging
    file_put_contents($repo.'/untracked.txt', 'content');

    $status = $this->git->status($repo);

    expect($status->untracked)->toContain('untracked.txt')
        ->and($status->staged)->toBeEmpty()
        ->and($status->unstaged)->toBeEmpty()
        ->and($status->isClean())->toBeFalse();
});

it('provides isClean and hasChanges helpers', function () {
    $repo = $this->createTestRepository();

    // Clean repo
    $status = $this->git->status($repo);
    expect($status->isClean())->toBeTrue()
        ->and($status->hasChanges())->toBeFalse();

    // Add untracked file
    file_put_contents($repo.'/file.txt', 'content');
    $status = $this->git->status($repo);
    expect($status->isClean())->toBeFalse()
        ->and($status->hasChanges())->toBeTrue();
});

it('returns empty diff for clean repo', function () {
    $repo = $this->createTestRepository();

    $diff = $this->git->diff($repo);

    expect($diff)->toBeEmpty();
});

it('returns diff for modified file', function () {
    $repo = $this->createTestRepository();

    // Create and commit a file
    file_put_contents($repo.'/file.txt', 'original content');
    $this->git->add($repo, 'file.txt');
    $this->git->commit($repo, 'Initial commit');

    // Modify the file
    file_put_contents($repo.'/file.txt', 'modified content');

    $diff = $this->git->diff($repo);

    expect($diff)->toContain('original content')
        ->and($diff)->toContain('modified content')
        ->and($diff)->toContain('file.txt');
});

it('shows staged changes with diff staged', function () {
    $repo = $this->createTestRepository();

    // Create and commit a file
    file_put_contents($repo.'/file.txt', 'original content');
    $this->git->add($repo, 'file.txt');
    $this->git->commit($repo, 'Initial commit');

    // Modify and stage the file
    file_put_contents($repo.'/file.txt', 'staged content');
    $this->git->add($repo, 'file.txt');

    // Regular diff should be empty
    expect($this->git->diff($repo))->toBeEmpty();

    // Staged diff should show changes
    $stagedDiff = $this->git->diff($repo, staged: true);
    expect($stagedDiff)->toContain('original content')
        ->and($stagedDiff)->toContain('staged content');
});
