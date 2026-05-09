<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Graft\Ai\Tools\GitLogTool;
use Graft\Data\Git\Commit;
use Graft\Facades\Git;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitLogTool;
});

it('returns the documented tool id', function () {
    expect(GitLogTool::toolId())->toBe('graft:git:log');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo_path and limit fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo_path', 'limit']);
});

it('returns formatted commit data on success', function () {
    $fake = Git::fake();
    $fake->shouldReturn('log', collect([
        new Commit(
            hash: 'abc123def456789',
            shortHash: 'abc123d',
            message: 'Initial commit',
            author: 'Joey',
            email: 'joey@example.com',
            date: CarbonImmutable::parse('2026-05-01T12:00:00Z'),
        ),
        new Commit(
            hash: 'def456abc789123',
            shortHash: 'def456a',
            message: 'Second commit',
            author: 'Joey',
            email: 'joey@example.com',
            date: CarbonImmutable::parse('2026-05-02T12:00:00Z'),
        ),
    ]));

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo', 'limit' => 5]));

    $data = json_decode($output, true);
    expect($data['count'])->toBe(2)
        ->and($data['commits'][0]['hash'])->toBe('abc123d')
        ->and($data['commits'][0]['message'])->toBe('Initial commit')
        ->and($data['commits'][0]['author'])->toBe('Joey');

    $fake->assertCalled('log', fn ($args) => $args[0] === '/tmp/repo' && $args[1] === 5);
});

it('returns a "no commits" message when empty', function () {
    Git::fake()->shouldReturn('log', collect());

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo']));

    expect($output)->toBe('No commits found.');
});

it('returns an Error message when the underlying call throws', function () {
    Git::fake()->shouldThrow('log', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo_path' => '/tmp/repo']));

    expect($output)->toStartWith('Error');
});
