<?php

use Carbon\CarbonImmutable;
use Graft\Data\Git\Commit;
use Graft\Exceptions\ProcessException;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

test('commit creates a commit and returns Commit DTO', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file.txt', 'content');
    $this->runGit($repo, ['add', '.']);

    $commit = $this->git->commit($repo, 'Initial commit');

    expect($commit)->toBeInstanceOf(Commit::class)
        ->and($commit->message)->toBe('Initial commit')
        ->and($commit->hash)->toBeString()->toHaveLength(40)
        ->and($commit->shortHash)->toBeString()->toHaveLength(7)
        ->and($commit->author)->toBeString()
        ->and($commit->email)->toBeString()
        ->and($commit->date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($commit->parents)->toBeArray()->toBeEmpty();
});

test('commit with allowEmpty flag', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file.txt', 'content');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'First commit');

    $commit = $this->git->commit($repo, 'Empty commit', true);

    expect($commit)->toBeInstanceOf(Commit::class)
        ->and($commit->message)->toBe('Empty commit');
});

test('commit with noVerify skips hooks', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file.txt', 'content');
    $this->runGit($repo, ['add', '.']);

    $hook = $repo.'/.git/hooks/pre-commit';
    file_put_contents($hook, "#!/bin/sh\nexit 1\n");
    chmod($hook, 0755);

    expect(fn () => $this->git->commit($repo, 'Should fail'))
        ->toThrow(ProcessException::class);

    $commit = $this->git->commit($repo, 'Skip hooks', noVerify: true);

    expect($commit)->toBeInstanceOf(Commit::class)
        ->and($commit->message)->toBe('Skip hooks');
});

test('log and show round-trip a commit subject containing a pipe', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file.txt', 'content');
    $this->runGit($repo, ['add', '.']);

    $message = 'feat: parse A | B | C correctly';
    $commit = $this->git->commit($repo, $message);

    expect($commit->message)->toBe($message);

    $shown = $this->git->show($repo, $commit->hash);
    expect($shown->message)->toBe($message)
        ->and($shown->author)->toBe($commit->author)
        ->and($shown->email)->toBe($commit->email)
        ->and($shown->hash)->toBe($commit->hash);

    $logged = $this->git->log($repo, 1);
    expect($logged)->toHaveCount(1)
        ->and($logged->first()->message)->toBe($message)
        ->and($logged->first()->hash)->toBe($commit->hash);
});

test('log returns collection of commits', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file1.txt', 'content1');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'First commit');
    $this->createFileInRepo($repo, 'file2.txt', 'content2');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'Second commit');
    $this->createFileInRepo($repo, 'file3.txt', 'content3');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'Third commit');

    $commits = $this->git->log($repo);

    expect($commits)->toHaveCount(3)
        ->and($commits->first()->message)->toBe('Third commit')
        ->and($commits->last()->message)->toBe('First commit');
});

test('log with limit', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file1.txt', 'content1');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'First commit');
    $this->createFileInRepo($repo, 'file2.txt', 'content2');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'Second commit');
    $this->createFileInRepo($repo, 'file3.txt', 'content3');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'Third commit');

    $commits = $this->git->log($repo, 2);

    expect($commits)->toHaveCount(2)
        ->and($commits->first()->message)->toBe('Third commit')
        ->and($commits->last()->message)->toBe('Second commit');
});

test('log with ref', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file1.txt', 'content1');
    $this->runGit($repo, ['add', '.']);
    $firstCommit = $this->git->commit($repo, 'First commit');
    $this->createFileInRepo($repo, 'file2.txt', 'content2');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'Second commit');

    $commits = $this->git->log($repo, 10, $firstCommit->hash);

    expect($commits)->toHaveCount(1)
        ->and($commits->first()->message)->toBe('First commit');
});

test('show returns single commit', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file.txt', 'content');
    $this->runGit($repo, ['add', '.']);
    $createdCommit = $this->git->commit($repo, 'Test commit');

    $commit = $this->git->show($repo, 'HEAD');

    expect($commit)->toBeInstanceOf(Commit::class)
        ->and($commit->message)->toBe('Test commit')
        ->and($commit->hash)->toBe($createdCommit->hash);
});

test('head returns hash string', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file.txt', 'content');
    $this->runGit($repo, ['add', '.']);
    $commit = $this->git->commit($repo, 'Test commit');

    $head = $this->git->head($repo);

    expect($head)->toBeString()->toHaveLength(40)
        ->and($head)->toBe($commit->hash);
});

test('commit DTO has correct fields', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'file.txt', 'content');
    $this->runGit($repo, ['add', '.']);

    $commit = $this->git->commit($repo, 'Test commit message');

    expect($commit->hash)->toBeString()->toHaveLength(40)
        ->and($commit->shortHash)->toBeString()->toHaveLength(7)
        ->and($commit->message)->toBe('Test commit message')
        ->and($commit->author)->toBeString()->not()->toBeEmpty()
        ->and($commit->email)->toBeString()->toContain('@')
        ->and($commit->date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($commit->parents)->toBeArray();
});

test('merge commit has multiple parents', function () {
    $repo = $this->createTestRepository();

    // Create main branch with a commit
    $this->createFileInRepo($repo, 'main.txt', 'main content');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'Main commit');

    // Create and switch to feature branch
    $this->runGit($repo, ['checkout', '-b', 'feature']);
    $this->createFileInRepo($repo, 'feature.txt', 'feature content');
    $this->runGit($repo, ['add', '.']);
    $this->git->commit($repo, 'Feature commit');

    // Switch back to main and merge
    $this->runGit($repo, ['checkout', 'main']);
    $this->runGit($repo, ['merge', 'feature', '--no-ff', '-m', 'Merge feature branch']);

    $mergeCommit = $this->git->show($repo, 'HEAD');

    expect($mergeCommit->message)->toBe('Merge feature branch')
        ->and($mergeCommit->parents)->toHaveCount(2);
});
