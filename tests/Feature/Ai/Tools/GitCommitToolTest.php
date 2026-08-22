<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Graft\Ai\Tools\GitCommitTool;
use Graft\Data\Git\Commit;
use Graft\Facades\Git;
use Graft\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new GitCommitTool;
    $this->repoPath = allowlistedRepoPath();
});

it('returns the documented tool id', function () {
    expect(GitCommitTool::toolId())->toBe('graft:git:commit');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo_path, message, paths, and allow_empty fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo_path', 'message', 'paths', 'allow_empty']);
});

it('stages paths and creates a commit', function () {
    $fake = Git::fake();
    $fake->shouldReturn('commit', new Commit(
        hash: 'abc123def456',
        shortHash: 'abc123d',
        message: 'Add feature',
        author: 'Joey',
        email: 'joey@example.com',
        date: CarbonImmutable::parse('2026-05-01T12:00:00Z'),
    ));

    $output = $this->tool->handle(new Request([
        'repo_path' => $this->repoPath,
        'message' => 'Add feature',
        'paths' => ['src/Foo.php', 'README.md'],
    ]));

    $data = json_decode($output, true);
    expect($data['hash'])->toBe('abc123d')
        ->and($data['message'])->toBe('Add feature')
        ->and($data['allow_empty'])->toBeFalse();

    $fake->assertCalled('add', fn ($args) => $args[0] === $this->repoPath && $args[1] === ['src/Foo.php', 'README.md']);
    $fake->assertCalled('commit', fn ($args) => $args[0] === $this->repoPath && $args[1] === 'Add feature' && $args[2] === false);
});

it('defaults staged paths to the whole tree and supports allow_empty', function () {
    $fake = Git::fake();

    $this->tool->handle(new Request([
        'repo_path' => $this->repoPath,
        'message' => 'Empty commit',
        'allow_empty' => true,
    ]));

    $fake->assertCalled('add', fn ($args) => $args[1] === '.');
    $fake->assertCalled('commit', fn ($args) => $args[1] === 'Empty commit' && $args[2] === true);
});

it('returns a structured error when the underlying call throws', function () {
    Git::fake()->shouldThrow('commit', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request([
        'repo_path' => $this->repoPath,
        'message' => 'Add feature',
    ]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
