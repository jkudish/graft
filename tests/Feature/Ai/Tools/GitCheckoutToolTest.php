<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitCheckoutTool;
use Graft\Facades\Git;
use Graft\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new GitCheckoutTool;
    $this->repoPath = allowlistedRepoPath();
});

it('returns the documented tool id', function () {
    expect(GitCheckoutTool::toolId())->toBe('graft:git:checkout');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo_path, branch, and create fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo_path', 'branch', 'create']);
});

it('checks out a branch', function () {
    $fake = Git::fake();

    $output = $this->tool->handle(new Request([
        'repo_path' => $this->repoPath,
        'branch' => 'feature/payments',
    ]));

    $data = json_decode($output, true);
    expect($data['ok'])->toBeTrue()
        ->and($data['branch'])->toBe('feature/payments')
        ->and($data['created'])->toBeFalse();

    $fake->assertCalled('checkout', fn ($args) => $args[0] === $this->repoPath && $args[1] === 'feature/payments' && $args[2] === false);
});

it('creates and checks out a branch when create is true', function () {
    $fake = Git::fake();

    $output = $this->tool->handle(new Request([
        'repo_path' => $this->repoPath,
        'branch' => 'feature/new',
        'create' => true,
    ]));

    $data = json_decode($output, true);
    expect($data['created'])->toBeTrue();

    $fake->assertCalled('checkout', fn ($args) => $args[1] === 'feature/new' && $args[2] === true);
});

it('returns a structured error when the underlying call throws', function () {
    Git::fake()->shouldThrow('checkout', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request([
        'repo_path' => $this->repoPath,
        'branch' => 'main',
    ]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
