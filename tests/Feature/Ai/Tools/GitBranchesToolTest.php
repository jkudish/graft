<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitBranchesTool;
use Graft\Data\Git\Branch;
use Graft\Facades\Git;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitBranchesTool;
    $this->repoPath = allowlistedRepoPath();
});

it('returns the documented tool id', function () {
    expect(GitBranchesTool::toolId())->toBe('graft:git:branches');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo_path and remote fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo_path', 'remote']);
});

it('returns formatted branch data on success', function () {
    $fake = Git::fake();
    $fake->shouldReturn('branches', collect([
        new Branch(name: 'main', isCurrent: true, isRemote: false, upstream: 'origin/main'),
        new Branch(name: 'feature/foo', isCurrent: false, isRemote: false),
    ]));

    $output = $this->tool->handle(new Request(['repo_path' => $this->repoPath, 'remote' => true]));

    $data = json_decode($output, true);
    expect($data['count'])->toBe(2)
        ->and($data['branches'][0]['name'])->toBe('main')
        ->and($data['branches'][0]['is_current'])->toBeTrue()
        ->and($data['branches'][0]['upstream'])->toBe('origin/main')
        ->and($data['branches'][1]['name'])->toBe('feature/foo');

    $fake->assertCalled('branches', fn ($args) => $args[0] === $this->repoPath && $args[1] === true);
});

it('returns "No branches found" when collection is empty', function () {
    Git::fake()->shouldReturn('branches', collect());

    $output = $this->tool->handle(new Request(['repo_path' => $this->repoPath]));

    expect($output)->toBe('No branches found.');
});

it('returns a structured error when the underlying call throws', function () {
    Git::fake()->shouldThrow('branches', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo_path' => $this->repoPath]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
