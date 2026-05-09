<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitStatusTool;
use Graft\Data\Git\Status;
use Graft\Facades\Git;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitStatusTool;
});

it('returns the documented tool id', function () {
    expect(GitStatusTool::toolId())->toBe('graft:git:status');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo_path field', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('repo_path');
});

it('returns formatted status data on success', function () {
    $fake = Git::fake();
    $fake->shouldReturn('status', new Status(
        staged: ['app/Foo.php'],
        unstaged: ['app/Bar.php'],
        untracked: ['app/Baz.php'],
    ));

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo']));

    $data = json_decode($output, true);
    expect($data['is_clean'])->toBeFalse()
        ->and($data['staged'])->toBe(['app/Foo.php'])
        ->and($data['unstaged'])->toBe(['app/Bar.php'])
        ->and($data['untracked'])->toBe(['app/Baz.php']);

    $fake->assertCalled('status', fn ($args) => $args[0] === '/tmp/repo');
});

it('reports a clean status when all collections are empty', function () {
    Git::fake()->shouldReturn('status', new Status(staged: [], unstaged: [], untracked: []));

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo']));

    $data = json_decode($output, true);
    expect($data['is_clean'])->toBeTrue();
});

it('returns an Error message when the underlying call throws', function () {
    Git::fake()->shouldThrow('status', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo']));

    expect($output)->toStartWith('Error');
});
