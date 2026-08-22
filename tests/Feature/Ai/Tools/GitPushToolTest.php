<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitPushTool;
use Graft\Facades\Git;
use Graft\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new GitPushTool;
    $this->repoPath = allowlistedRepoPath();
});

it('returns the documented tool id', function () {
    expect(GitPushTool::toolId())->toBe('graft:git:push');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with remote, branch, set_upstream, and force fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo_path', 'remote', 'branch', 'set_upstream', 'force']);
});

it('pushes the current branch', function () {
    $fake = Git::fake();

    $output = $this->tool->handle(new Request(['repo_path' => $this->repoPath]));

    $data = json_decode($output, true);
    expect($data['ok'])->toBeTrue()
        ->and($data['remote'])->toBeNull()
        ->and($data['branch'])->toBeNull()
        ->and($data['force'])->toBeFalse()
        ->and($data['set_upstream'])->toBeFalse();

    $fake->assertCalled('push', fn ($args) => $args === [$this->repoPath, null, null, false, false]);
});

it('pushes a named branch with upstream and force flags', function () {
    $fake = Git::fake();

    $output = $this->tool->handle(new Request([
        'repo_path' => $this->repoPath,
        'remote' => 'origin',
        'branch' => 'feature/x',
        'set_upstream' => true,
        'force' => true,
    ]));

    $data = json_decode($output, true);
    expect($data['remote'])->toBe('origin')
        ->and($data['branch'])->toBe('feature/x')
        ->and($data['set_upstream'])->toBeTrue()
        ->and($data['force'])->toBeTrue();

    $fake->assertCalled('push', fn ($args) => $args === [$this->repoPath, 'origin', 'feature/x', true, true]);
});

it('returns a structured error when the underlying call throws', function () {
    Git::fake()->shouldThrow('push', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo_path' => $this->repoPath]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
