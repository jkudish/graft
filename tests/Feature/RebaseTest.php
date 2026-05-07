<?php

declare(strict_types=1);

use Graft\Exceptions\ProcessException;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

test('successful rebase', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Create feature branch
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'feature.txt', 'feature content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature']);

    // Add commit to main
    $this->runGit($repo, ['checkout', $mainBranch]);
    $this->createFileInRepo($repo, 'main.txt', 'main content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add main file']);

    // Rebase feature onto main
    $this->runGit($repo, ['checkout', 'feature']);
    $this->git->rebase($repo, $mainBranch);

    // Verify rebase succeeded
    $log = $this->git->log($repo, 3);
    expect($log->first()->message)->toBe('Add feature')
        ->and($log->pluck('message')->toArray())->toContain('Add main file')
        ->and($log->pluck('message')->toArray())->toContain('Initial commit');
});

test('rebase with conflict throws ProcessException', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Modify README on main
    $this->createFileInRepo($repo, 'README.md', 'Main branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on main']);

    // Create feature branch from before the main change
    $this->runGit($repo, ['checkout', 'HEAD~1']);
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'README.md', 'Feature branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on feature']);

    // Try to rebase - should throw exception
    expect(fn () => $this->git->rebase($repo, $mainBranch))->toThrow(ProcessException::class);

    // Cleanup
    $this->git->rebaseAbort($repo);
});

test('rebaseAbort cancels rebase', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Modify README on main
    $this->createFileInRepo($repo, 'README.md', 'Main branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on main']);

    // Create feature branch from before the main change
    $this->runGit($repo, ['checkout', 'HEAD~1']);
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'README.md', 'Feature branch content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on feature']);

    // Start rebase and expect conflict
    try {
        $this->git->rebase($repo, $mainBranch);
    } catch (ProcessException) {
        // Expected
    }

    // Abort the rebase
    $this->git->rebaseAbort($repo);

    // Verify no rebase is in progress
    $status = $this->runGit($repo, ['status']);
    expect($status)->not()->toContain('rebase in progress');
});
