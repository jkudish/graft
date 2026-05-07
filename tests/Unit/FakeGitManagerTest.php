<?php

use Graft\Data\Git\Commit;
use Graft\Data\Git\Status;
use Graft\Facades\Git;
use Graft\ScopedRepository;
use Graft\Testing\FakeGitManager;
use Illuminate\Support\Collection;

test('records method calls', function () {
    $fake = new FakeGitManager;

    $fake->init('/tmp/test');
    $fake->checkout('/tmp/test', 'main');
    $fake->commit('/tmp/test', 'Initial commit');

    expect($fake)->toBeInstanceOf(FakeGitManager::class);
});

test('assertCalled verifies method was called', function () {
    $fake = new FakeGitManager;

    $fake->init('/tmp/test');

    $fake->assertCalled('init');
});

test('assertCalled with callback filters by arguments', function () {
    $fake = new FakeGitManager;

    $fake->init('/tmp/test');
    $fake->init('/tmp/other');

    $fake->assertCalled('init', fn ($args) => $args[0] === '/tmp/test');
    $fake->assertCalled('init', fn ($args) => $args[0] === '/tmp/other');
});

test('assertNotCalled verifies method was not called', function () {
    $fake = new FakeGitManager;

    $fake->init('/tmp/test');

    $fake->assertNotCalled('clone');
});

test('assertCalledTimes verifies call count', function () {
    $fake = new FakeGitManager;

    $fake->init('/tmp/test');
    $fake->init('/tmp/other');
    $fake->init('/tmp/another');

    $fake->assertCalledTimes('init', 3);
});

test('shouldReturn configures return values', function () {
    $fake = new FakeGitManager;

    $fake->shouldReturn('currentBranch', 'feature-branch');

    $branch = $fake->currentBranch('/tmp/test');

    expect($branch)->toBe('feature-branch');
});

test('shouldThrow throws configured exception', function () {
    $fake = new FakeGitManager;

    $exception = new RuntimeException('Repository not found');
    $fake->shouldThrow('init', $exception);

    expect(fn () => $fake->init('/tmp/test'))->toThrow(RuntimeException::class, 'Repository not found');
});

test('assertBranchCreated', function () {
    $fake = new FakeGitManager;

    $fake->createBranch('/tmp/test', 'feature-branch');

    $fake->assertBranchCreated('feature-branch');
});

test('assertBranchCreated with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->createBranch('/tmp/test', 'feature-branch');
    $fake->createBranch('/tmp/other', 'other-branch');

    $fake->assertBranchCreated('feature-branch', '/tmp/test');
});

test('assertCheckedOut', function () {
    $fake = new FakeGitManager;

    $fake->checkout('/tmp/test', 'main');

    $fake->assertCheckedOut('main');
});

test('assertCheckedOut with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->checkout('/tmp/test', 'main');
    $fake->checkout('/tmp/other', 'develop');

    $fake->assertCheckedOut('main', '/tmp/test');
});

test('assertCommitted', function () {
    $fake = new FakeGitManager;

    $fake->commit('/tmp/test', 'Initial commit');

    $fake->assertCommitted();
});

test('assertCommitted with message contains', function () {
    $fake = new FakeGitManager;

    $fake->commit('/tmp/test', 'feat: add new feature');
    $fake->commit('/tmp/test', 'fix: resolve bug');

    $fake->assertCommitted('add new feature');
    $fake->assertCommitted('resolve bug');
});

test('assertCommitted with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->commit('/tmp/test', 'Initial commit');
    $fake->commit('/tmp/other', 'Other commit');

    $fake->assertCommitted('Initial commit', '/tmp/test');
});

test('assertPushed', function () {
    $fake = new FakeGitManager;

    $fake->push('/tmp/test');

    $fake->assertPushed();
});

test('assertPushed with branch filter', function () {
    $fake = new FakeGitManager;

    $fake->push('/tmp/test', 'origin', 'main');
    $fake->push('/tmp/test', 'origin', 'develop');

    $fake->assertPushed('main');
    $fake->assertPushed('develop');
});

test('assertPushed with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->push('/tmp/test', 'origin', 'main');
    $fake->push('/tmp/other', 'origin', 'main');

    $fake->assertPushed('main', '/tmp/test');
});

test('assertPulled', function () {
    $fake = new FakeGitManager;

    $fake->pull('/tmp/test');

    $fake->assertPulled();
});

test('assertPulled with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->pull('/tmp/test');
    $fake->pull('/tmp/other');

    $fake->assertPulled('/tmp/test');
});

test('assertWorktreeAdded', function () {
    $fake = new FakeGitManager;

    $fake->addWorktree('/tmp/test', '/tmp/worktree');

    $fake->assertWorktreeAdded('/tmp/worktree');
});

test('assertWorktreeAdded with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->addWorktree('/tmp/test', '/tmp/worktree1');
    $fake->addWorktree('/tmp/other', '/tmp/worktree2');

    $fake->assertWorktreeAdded('/tmp/worktree1', '/tmp/test');
});

test('assertWorktreeRemoved', function () {
    $fake = new FakeGitManager;

    $fake->removeWorktree('/tmp/test', '/tmp/worktree');

    $fake->assertWorktreeRemoved('/tmp/worktree');
});

test('assertMerged', function () {
    $fake = new FakeGitManager;

    $fake->merge('/tmp/test', 'feature-branch');

    $fake->assertMerged('feature-branch');
});

test('assertMerged with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->merge('/tmp/test', 'feature-branch');
    $fake->merge('/tmp/other', 'other-branch');

    $fake->assertMerged('feature-branch', '/tmp/test');
});

test('assertTagCreated', function () {
    $fake = new FakeGitManager;

    $fake->createTag('/tmp/test', 'v1.0.0');

    $fake->assertTagCreated('v1.0.0');
});

test('assertTagCreated with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->createTag('/tmp/test', 'v1.0.0');
    $fake->createTag('/tmp/other', 'v2.0.0');

    $fake->assertTagCreated('v1.0.0', '/tmp/test');
});

test('assertCloned', function () {
    $fake = new FakeGitManager;

    $fake->clone('https://github.com/user/repo.git', '/tmp/test');

    $fake->assertCloned('https://github.com/user/repo.git');
});

test('assertInitialized', function () {
    $fake = new FakeGitManager;

    $fake->init('/tmp/test');

    $fake->assertInitialized('/tmp/test');
});

test('assertFetched', function () {
    $fake = new FakeGitManager;

    $fake->fetch('/tmp/test');

    $fake->assertFetched();
});

test('assertFetched with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->fetch('/tmp/test');
    $fake->fetch('/tmp/other');

    $fake->assertFetched('/tmp/test');
});

test('assertStashed', function () {
    $fake = new FakeGitManager;

    $fake->stash('/tmp/test');

    $fake->assertStashed();
});

test('assertStashed with repo path filter', function () {
    $fake = new FakeGitManager;

    $fake->stash('/tmp/test');
    $fake->stash('/tmp/other');

    $fake->assertStashed('/tmp/test');
});

test('assertNothingPushed', function () {
    $fake = new FakeGitManager;

    $fake->commit('/tmp/test', 'Initial commit');

    $fake->assertNothingPushed();
});

test('assertNothingCommitted', function () {
    $fake = new FakeGitManager;

    $fake->init('/tmp/test');

    $fake->assertNothingCommitted();
});

test('assertNothingCalled', function () {
    $fake = new FakeGitManager;

    $fake->assertNothingCalled();
});

test('Git::fake() swaps the facade', function () {
    $fake = Git::fake();

    Git::init('/tmp/test');

    $fake->assertInitialized('/tmp/test');
});

test('returns sensible defaults for collection methods', function () {
    $fake = new FakeGitManager;

    expect($fake->branches('/tmp/test'))->toBeInstanceOf(Collection::class);
    expect($fake->branches('/tmp/test'))->toBeEmpty();
});

test('returns sensible defaults for data objects', function () {
    $fake = new FakeGitManager;

    $status = $fake->status('/tmp/test');
    expect($status)->toBeInstanceOf(Status::class);
    expect($status->isClean())->toBeTrue();

    $commit = $fake->commit('/tmp/test', 'Test commit');
    expect($commit)->toBeInstanceOf(Commit::class);
    expect($commit->message)->toBe('Test commit');
});

test('returns sensible defaults for boolean methods', function () {
    $fake = new FakeGitManager;

    expect($fake->isRepository('/tmp/test'))->toBeTrue();
    expect($fake->branchExists('/tmp/test', 'main'))->toBeTrue();
});

test('returns sensible defaults for string methods', function () {
    $fake = new FakeGitManager;

    expect($fake->currentBranch('/tmp/test'))->toBe('main');
    expect($fake->head('/tmp/test'))->toBe('abc123def456');
    expect($fake->diff('/tmp/test'))->toBe('');
});

test('repo method returns scoped repository', function () {
    $fake = Git::fake();

    $repo = $fake->repo('/tmp/test');

    expect($repo)->toBeInstanceOf(ScopedRepository::class);
});
