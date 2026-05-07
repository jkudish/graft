<?php

declare(strict_types=1);

use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

test('cherry-pick single commit', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Create feature branch with a commit
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'feature.txt', 'feature content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature']);
    $featureCommit = $this->git->head($repo);

    // Switch back to main and cherry-pick
    $this->runGit($repo, ['checkout', $mainBranch]);
    $this->git->cherryPick($repo, $featureCommit);

    // Verify cherry-pick succeeded
    $log = $this->git->log($repo, 2);
    expect($log->first()->message)->toBe('Add feature');
    expect(file_exists($repo.'/feature.txt'))->toBeTrue();
});

test('cherry-pick multiple commits', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Create feature branch with multiple commits
    $this->runGit($repo, ['checkout', '-b', 'feature']);

    $this->createFileInRepo($repo, 'feature1.txt', 'feature 1 content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature 1']);
    $commit1 = $this->git->head($repo);

    $this->createFileInRepo($repo, 'feature2.txt', 'feature 2 content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Add feature 2']);
    $commit2 = $this->git->head($repo);

    // Switch back to main and cherry-pick both commits
    $this->runGit($repo, ['checkout', $mainBranch]);
    $this->git->cherryPick($repo, [$commit1, $commit2]);

    // Verify both cherry-picks succeeded
    $log = $this->git->log($repo, 3);
    expect($log->pluck('message')->toArray())->toContain('Add feature 1')
        ->and($log->pluck('message')->toArray())->toContain('Add feature 2');
    expect(file_exists($repo.'/feature1.txt'))->toBeTrue();
    expect(file_exists($repo.'/feature2.txt'))->toBeTrue();
});

test('cherryPickAbort cancels cherry-pick', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $mainBranch = $this->getMainBranch($repo);

    // Modify README on main
    $this->createFileInRepo($repo, 'README.md', 'Main content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on main']);
    $mainCommit = $this->git->head($repo);

    // Create feature branch from before the main change
    $this->runGit($repo, ['checkout', 'HEAD~1']);
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'README.md', 'Feature content');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Update README on feature']);

    // Try to cherry-pick conflicting commit
    try {
        $this->git->cherryPick($repo, $mainCommit);
    } catch (Exception) {
        // Expected to fail due to conflict
    }

    // Abort the cherry-pick
    $this->git->cherryPickAbort($repo);

    // Verify no cherry-pick is in progress
    $status = $this->runGit($repo, ['status']);
    expect($status)->not()->toContain('cherry-pick');
});
