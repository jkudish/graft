<?php

declare(strict_types=1);

use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

// Repository tests
test('init creates a new repository', function () {
    $path = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $path;

    $this->git->init($path);

    expect($this->git->isRepository($path))->toBeTrue();
});

test('init creates a bare repository', function () {
    $path = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $path;

    $this->git->init($path, bare: true);

    expect($this->git->isRepository($path))->toBeTrue();
    expect(is_file($path.'/HEAD'))->toBeTrue(); // bare repos have HEAD at root
});

test('isRepository returns false for non-repo directory', function () {
    $path = sys_get_temp_dir().'/graft-test-'.uniqid();
    mkdir($path, 0777, true);
    $this->testRepoPaths[] = $path;

    expect($this->git->isRepository($path))->toBeFalse();
});

test('clone clones a repository', function () {
    $source = $this->createTestRepositoryWithCommit();
    $dest = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $dest;

    $this->git->clone($source, $dest);

    expect($this->git->isRepository($dest))->toBeTrue();
});

test('clone clones a specific branch', function () {
    $source = $this->createTestRepositoryWithCommit();
    $this->runGit($source, ['checkout', '-b', 'develop']);
    $this->createFileInRepo($source, 'develop.txt', 'develop content');
    $this->runGit($source, ['add', '.']);
    $this->runGit($source, ['commit', '-m', 'develop commit']);

    $dest = sys_get_temp_dir().'/graft-test-'.uniqid();
    $this->testRepoPaths[] = $dest;

    $this->git->clone($source, $dest, branch: 'develop');

    expect($this->git->isRepository($dest))->toBeTrue();
    // Should be on develop branch — we can check by reading HEAD
    $branch = trim(shell_exec("cd {$dest} && git branch --show-current"));
    expect($branch)->toBe('develop');
});

// Config tests
test('setConfig and getConfig work', function () {
    $path = $this->createTestRepository();

    $this->git->setConfig($path, 'user.name', 'Test User');

    expect($this->git->getConfig($path, 'user.name'))->toBe('Test User');
});

test('getConfig returns null for missing key', function () {
    $path = $this->createTestRepository();

    expect($this->git->getConfig($path, 'nonexistent.key'))->toBeNull();
});

// Clean tests
test('clean removes untracked files', function () {
    $path = $this->createTestRepositoryWithCommit();
    $this->createFileInRepo($path, 'untracked.txt', 'junk');

    expect(file_exists($path.'/untracked.txt'))->toBeTrue();

    $this->git->clean($path);

    expect(file_exists($path.'/untracked.txt'))->toBeFalse();
});

test('clean with directories removes untracked directories', function () {
    $path = $this->createTestRepositoryWithCommit();
    mkdir($path.'/untracked-dir');
    file_put_contents($path.'/untracked-dir/file.txt', 'junk');

    $this->git->clean($path, directories: true);

    expect(is_dir($path.'/untracked-dir'))->toBeFalse();
});

// Blame tests
test('blame returns line information', function () {
    $path = $this->createTestRepository();
    $this->createFileInRepo($path, 'file.txt', "line1\nline2\nline3");
    $this->runGit($path, ['add', '.']);
    $this->runGit($path, ['commit', '-m', 'Add file']);

    $blames = $this->git->blame($path, 'file.txt');

    expect($blames)->toHaveCount(3);
    expect($blames[0]->lineNumber)->toBe(1);
    expect($blames[0]->content)->toBe('line1');
    expect($blames[0]->author)->toBe('Graft Test');
    expect($blames[1]->lineNumber)->toBe(2);
    expect($blames[2]->lineNumber)->toBe(3);
});
