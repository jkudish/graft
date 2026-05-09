<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitDiffTool;
use Graft\Facades\Git;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitDiffTool;
});

it('returns the documented tool id', function () {
    expect(GitDiffTool::toolId())->toBe('graft:git:diff');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo_path and staged fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo_path', 'staged']);
});

it('returns the diff string on success', function () {
    $diff = "diff --git a/foo b/foo\n+added line\n";

    $fake = Git::fake();
    $fake->shouldReturn('diff', $diff);

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo', 'staged' => true]));

    expect($output)->toBe($diff);
    $fake->assertCalled('diff', fn ($args) => $args[0] === '/tmp/repo' && $args[1] === true);
});

it('reports "No differences found" when diff is empty', function () {
    Git::fake()->shouldReturn('diff', '');

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo']));

    expect($output)->toBe('No differences found.');
});

it('returns an Error message when the underlying call throws', function () {
    Git::fake()->shouldThrow('diff', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo']));

    expect($output)->toStartWith('Error');
});
