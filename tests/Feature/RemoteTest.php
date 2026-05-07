<?php

declare(strict_types=1);

use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

test('addRemote adds a remote', function () {
    $repo = $this->createTestRepositoryWithCommit();

    $this->git->addRemote($repo, 'origin', 'https://github.com/user/repo.git');

    $remotes = $this->git->remotes($repo);
    expect($remotes)->toHaveCount(1);
    expect($remotes->first()->name)->toBe('origin');
    expect($remotes->first()->fetchUrl)->toBe('https://github.com/user/repo.git');
});

test('removeRemote removes a remote', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $this->git->addRemote($repo, 'origin', 'https://github.com/user/repo.git');

    $this->git->removeRemote($repo, 'origin');

    $remotes = $this->git->remotes($repo);
    expect($remotes)->toHaveCount(0);
});

test('remotes lists all remotes as Remote DTOs', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $this->git->addRemote($repo, 'origin', 'https://github.com/user/repo.git');
    $this->git->addRemote($repo, 'upstream', 'https://github.com/org/repo.git');

    $remotes = $this->git->remotes($repo);

    expect($remotes)->toHaveCount(2);
    expect($remotes->pluck('name')->toArray())->toContain('origin', 'upstream');
});

test('remotes returns fetch and push URLs', function () {
    $repo = $this->createTestRepositoryWithCommit();
    $this->git->addRemote($repo, 'origin', 'https://github.com/user/repo.git');

    $remotes = $this->git->remotes($repo);
    $origin = $remotes->firstWhere('name', 'origin');

    expect($origin->fetchUrl)->toBe('https://github.com/user/repo.git');
    expect($origin->pushUrl)->toBe('https://github.com/user/repo.git');
});

test('fetch fetches from remote', function () {
    // Create source repo with a commit
    $source = $this->createTestRepositoryWithCommit();

    // Create bare remote
    $remote = $this->createTestRepository(bare: true);

    // Add remote to source and push
    $this->runGit($source, ['remote', 'add', 'origin', $remote]);
    $this->runGit($source, ['push', '-u', 'origin', 'main']);

    // Create second clone of the bare repo
    $clone = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $clone;
    $this->git->clone($remote, $clone);

    // Add new commit to source
    $this->createFileInRepo($source, 'newfile.txt', 'new content');
    $this->runGit($source, ['add', '.']);
    $this->runGit($source, ['commit', '-m', 'Add new file']);
    $this->runGit($source, ['push', 'origin', 'main']);

    // Fetch in clone should succeed
    $this->git->fetch($clone, 'origin');

    // Verify fetch worked by checking remote branch exists
    $output = $this->runGit($clone, ['branch', '-r']);
    expect($output)->toContain('origin/main');
});

test('push pushes to remote', function () {
    // Create source repo with a commit
    $source = $this->createTestRepositoryWithCommit();

    // Create bare remote
    $remote = $this->createTestRepository(bare: true);

    // Add remote and push
    $this->git->addRemote($source, 'origin', $remote);

    // Get current branch name
    $branch = $this->runGit($source, ['branch', '--show-current']);

    $this->git->push($source, 'origin', $branch);

    // Verify push worked by checking remote has the commit
    $remoteBranches = shell_exec("cd {$remote} && git branch");
    expect($remoteBranches)->toContain($branch);
});

test('pull pulls from remote', function () {
    // Create source repo with a commit
    $source = $this->createTestRepositoryWithCommit();

    // Create bare remote
    $remote = $this->createTestRepository(bare: true);

    // Setup remote and push
    $this->runGit($source, ['remote', 'add', 'origin', $remote]);
    $branch = $this->runGit($source, ['branch', '--show-current']);
    $this->runGit($source, ['push', '-u', 'origin', $branch]);

    // Create second clone
    $clone = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $clone;
    $this->git->clone($remote, $clone);

    // Add new commit to source and push
    $this->createFileInRepo($source, 'newfile.txt', 'new content');
    $this->runGit($source, ['add', '.']);
    $this->runGit($source, ['commit', '-m', 'Add new file']);
    $this->runGit($source, ['push', 'origin', $branch]);

    // Pull in clone
    $this->git->pull($clone, 'origin', $branch);

    // Verify file exists
    expect(file_exists($clone.'/newfile.txt'))->toBeTrue();
});

test('push with setUpstream flag', function () {
    // Create source repo with a commit
    $source = $this->createTestRepositoryWithCommit();

    // Create bare remote
    $remote = $this->createTestRepository(bare: true);

    // Add remote
    $this->git->addRemote($source, 'origin', $remote);

    // Get current branch name
    $branch = $this->runGit($source, ['branch', '--show-current']);

    // Push with -u flag
    $this->git->push($source, 'origin', $branch, setUpstream: true);

    // Verify tracking branch was set
    $upstream = trim(shell_exec("cd {$source} && git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>&1"));
    expect($upstream)->toBe("origin/{$branch}");
});

test('pull with noRebase flag passes --no-rebase', function () {
    $source = $this->createTestRepositoryWithCommit();
    $remote = $this->createTestRepository(bare: true);

    $this->runGit($source, ['remote', 'add', 'origin', $remote]);
    $branch = $this->runGit($source, ['branch', '--show-current']);
    $this->runGit($source, ['push', '-u', 'origin', $branch]);

    $clone = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $clone;
    $this->git->clone($remote, $clone);

    // Add new commit to source and push
    $this->createFileInRepo($source, 'newfile.txt', 'new content');
    $this->runGit($source, ['add', '.']);
    $this->runGit($source, ['commit', '-m', 'Add new file']);
    $this->runGit($source, ['push', 'origin', $branch]);

    // Pull with noRebase in clone — should succeed
    $this->git->pull($clone, 'origin', $branch, noRebase: true);

    expect(file_exists($clone.'/newfile.txt'))->toBeTrue();
});

test('fetch with prune flag', function () {
    // Create source repo with a commit
    $source = $this->createTestRepositoryWithCommit();

    // Create bare remote
    $remote = $this->createTestRepository(bare: true);

    // Setup remote and push
    $this->runGit($source, ['remote', 'add', 'origin', $remote]);
    $branch = $this->runGit($source, ['branch', '--show-current']);
    $this->runGit($source, ['push', '-u', 'origin', $branch]);

    // Create a feature branch and push it
    $this->runGit($source, ['checkout', '-b', 'feature/test']);
    $this->createFileInRepo($source, 'feature.txt', 'feature content');
    $this->runGit($source, ['add', '.']);
    $this->runGit($source, ['commit', '-m', 'Feature commit']);
    $this->runGit($source, ['push', 'origin', 'feature/test']);

    // Clone the repo
    $clone = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $clone;
    $this->git->clone($remote, $clone);

    // Delete the feature branch from remote
    $this->runGit($source, ['push', 'origin', '--delete', 'feature/test']);

    // Fetch with prune in clone
    $this->git->fetch($clone, 'origin', prune: true);

    // Verify remote branch was pruned
    $remoteBranches = $this->runGit($clone, ['branch', '-r']);
    expect($remoteBranches)->not->toContain('origin/feature/test');
});
