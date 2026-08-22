<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitHubMergePrTool;
use Graft\Facades\GitHub;
use Graft\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new GitHubMergePrTool;
});

it('returns the documented tool id', function () {
    expect(GitHubMergePrTool::toolId())->toBe('graft:github:merge-pr');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo, number, and method fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'number', 'method']);
});

it('merges a pull request with the requested method', function () {
    $fake = GitHub::fake();

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 42,
        'method' => 'squash',
    ]));

    $data = json_decode($output, true);
    expect($data['ok'])->toBeTrue()
        ->and($data['number'])->toBe(42)
        ->and($data['method'])->toBe('squash');

    $fake->assertCalled('mergePullRequest', fn ($args) => $args === ['owner/repo', 42, 'squash']);
});

it('defaults the merge method to squash', function () {
    $fake = GitHub::fake();

    $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 7,
    ]));

    $fake->assertCalled('mergePullRequest', fn ($args) => $args === ['owner/repo', 7, 'squash']);
});

it('returns a structured error for an invalid merge method', function () {
    $fake = GitHub::fake();

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 42,
        'method' => 'fast-forward',
    ]));

    expect(decodeToolError($output)['message'])->toContain('squash, merge, or rebase');
    $fake->assertNotCalled('mergePullRequest');
});

it('returns a structured error when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('mergePullRequest', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 42,
        'method' => 'merge',
    ]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
