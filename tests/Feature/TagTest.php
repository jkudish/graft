<?php

declare(strict_types=1);

use Graft\Exceptions\TagException;
use Graft\ProcessGitManager;
use Graft\Tests\Concerns\CreatesTestRepositories;

uses(CreatesTestRepositories::class);

beforeEach(function () {
    $this->git = new ProcessGitManager;
});

it('returns empty collection on fresh repo with no tags', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'README.md', '# Test');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Initial commit']);

    $tags = $this->git->tags($repo);

    expect($tags)->toBeEmpty();
});

it('creates a lightweight tag', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'README.md', '# Test');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Initial commit']);

    $this->git->createTag($repo, 'v1.0.0');

    $tags = $this->git->tags($repo);
    expect($tags)->toHaveCount(1);
    expect($tags->first())->toBe('v1.0.0');
});

it('creates an annotated tag with message', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'README.md', '# Test');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Initial commit']);

    $this->git->createTag($repo, 'v1.0.0', 'Release version 1.0.0');

    $tags = $this->git->tags($repo);
    expect($tags)->toHaveCount(1);
    expect($tags->first())->toBe('v1.0.0');
});

it('creates tag at specific ref', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'README.md', '# Test');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Initial commit']);
    $this->git->createBranch($repo, 'feature/test');
    $this->git->checkout($repo, 'feature/test');

    $this->createFileInRepo($repo, 'feature.txt', 'Feature work');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Feature work']);

    $mainBranch = $this->git->branches($repo)->filter(fn ($b) => ! $b->isCurrent)->first()->name;
    $commit = $this->git->branches($repo)->filter(fn ($b) => $b->name === $mainBranch)->first()->head;

    $this->git->createTag($repo, 'v0.9.0', null, $commit);

    $tags = $this->git->tags($repo);
    expect($tags)->toHaveCount(1);
    expect($tags->first())->toBe('v0.9.0');
});

it('lists all tags', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'README.md', '# Test');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Initial commit']);

    $this->git->createTag($repo, 'v1.0.0');
    $this->git->createTag($repo, 'v1.1.0');
    $this->git->createTag($repo, 'v1.2.0');

    $tags = $this->git->tags($repo);

    expect($tags)->toHaveCount(3);
    expect($tags)->toContain('v1.0.0');
    expect($tags)->toContain('v1.1.0');
    expect($tags)->toContain('v1.2.0');
});

it('deletes a tag', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'README.md', '# Test');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Initial commit']);
    $this->git->createTag($repo, 'v1.0.0');

    $this->git->deleteTag($repo, 'v1.0.0');

    $tags = $this->git->tags($repo);
    expect($tags)->toBeEmpty();
});

it('throws exception when deleting non-existent tag', function () {
    $repo = $this->createTestRepository();
    $this->createFileInRepo($repo, 'README.md', '# Test');
    $this->runGit($repo, ['add', '.']);
    $this->runGit($repo, ['commit', '-m', 'Initial commit']);

    $this->git->deleteTag($repo, 'non-existent-tag');
})->throws(TagException::class);
