<?php

declare(strict_types=1);

use Graft\Data\Git\Stash;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

it('stash saves working directory changes', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Modify a tracked file
    $this->createFileInRepo($repo, 'README.md', 'Modified content');

    // Stash the changes
    $this->git->stash($repo);

    // Check that the file is back to original state
    $content = file_get_contents($repo.'/README.md');
    expect($content)->toBe('# Test Repository');
});

it('stash with message', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Modify a tracked file
    $this->createFileInRepo($repo, 'README.md', 'Modified content');

    // Stash with message
    $this->git->stash($repo, 'My stash message');

    // List stashes and verify message
    $stashes = $this->git->stashList($repo);
    expect($stashes)->toHaveCount(1);
    expect($stashes->first()->message)->toContain('My stash message');
});

it('stash with includeUntracked', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Modify a tracked file
    $this->createFileInRepo($repo, 'README.md', 'Modified content');

    // Create an untracked file
    $this->createFileInRepo($repo, 'untracked.txt', 'Untracked content');

    // Stash with untracked files
    $this->git->stash($repo, includeUntracked: true);

    // Check that both files are removed
    expect(file_exists($repo.'/README.md'))->toBeTrue();
    expect(file_exists($repo.'/untracked.txt'))->toBeFalse();
});

it('stashPop restores changes', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Modify a tracked file
    $this->createFileInRepo($repo, 'README.md', 'Modified content');

    // Stash the changes
    $this->git->stash($repo);

    // Verify file is back to original
    expect(file_get_contents($repo.'/README.md'))->toBe('# Test Repository');

    // Pop the stash
    $this->git->stashPop($repo);

    // Check that the modification is restored
    expect(file_get_contents($repo.'/README.md'))->toBe('Modified content');
});

it('stashList returns collection of Stash DTOs', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Create multiple stashes
    $this->createFileInRepo($repo, 'README.md', 'Change 1');
    $this->git->stash($repo, 'First stash');

    $this->createFileInRepo($repo, 'README.md', 'Change 2');
    $this->git->stash($repo, 'Second stash');

    $stashes = $this->git->stashList($repo);

    expect($stashes)->toHaveCount(2);
    expect($stashes->first())->toBeInstanceOf(Stash::class);
    expect($stashes->first()->message)->toContain('Second stash');
    expect($stashes->first()->index)->toBe(0);
    expect($stashes->first()->hash)->toMatch('/^[0-9a-f]{40}$/');

    expect($stashes->get(1)->message)->toContain('First stash');
    expect($stashes->get(1)->index)->toBe(1);
});

it('stashDrop removes a stash entry', function () {
    $repo = $this->createTestRepositoryWithCommit();

    // Create two stashes
    $this->createFileInRepo($repo, 'README.md', 'Change 1');
    $this->git->stash($repo, 'First stash');

    $this->createFileInRepo($repo, 'README.md', 'Change 2');
    $this->git->stash($repo, 'Second stash');

    // Verify two stashes exist
    expect($this->git->stashList($repo))->toHaveCount(2);

    // Drop the first stash (index 0)
    $this->git->stashDrop($repo, 0);

    // Verify only one stash remains
    $stashes = $this->git->stashList($repo);
    expect($stashes)->toHaveCount(1);
    expect($stashes->first()->message)->toContain('First stash');
});

it('stashList returns empty collection when no stashes', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $stashes = $this->git->stashList($repo);

    expect($stashes)->toBeEmpty();
});
