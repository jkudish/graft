<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitHubCreateIssueTool;
use Graft\Data\Platform\Issue;
use Graft\Facades\GitHub;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitHubCreateIssueTool;
});

it('returns the documented tool id', function () {
    expect(GitHubCreateIssueTool::toolId())->toBe('graft:github:create-issue');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo, title, body, and labels fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'title', 'body', 'labels']);
});

it('creates an issue and returns formatted data', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('createIssue', new Issue(
        number: 99,
        title: 'New issue',
        body: 'Description',
        state: 'open',
        url: 'https://github.com/owner/repo/issues/99',
        author: 'alice',
        labels: ['bug', 'priority'],
    ));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'title' => 'New issue',
        'body' => 'Description',
        'labels' => '["bug","priority"]',
    ]));

    $data = json_decode($output, true);
    expect($data['number'])->toBe(99)
        ->and($data['title'])->toBe('New issue')
        ->and($data['url'])->toBe('https://github.com/owner/repo/issues/99')
        ->and($data['labels'])->toBe(['bug', 'priority']);

    $fake->assertCalled('createIssue', function ($args) {
        return $args[0] === 'owner/repo'
            && $args[1] === 'New issue'
            && $args[2] === 'Description'
            && $args[3] === ['bug', 'priority'];
    });
});

it('handles missing labels parameter without error', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('createIssue', new Issue(
        number: 1,
        title: 't',
        body: 'b',
        state: 'open',
        url: 'https://github.com/owner/repo/issues/1',
        author: 'alice',
    ));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'title' => 't',
        'body' => 'b',
    ]));

    $data = json_decode($output, true);
    expect($data['number'])->toBe(1);

    $fake->assertCalled('createIssue', fn ($args) => $args[3] === []);
});

it('returns an Error message when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('createIssue', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'title' => 't',
        'body' => 'b',
    ]));

    expect($output)->toStartWith('Error');
});
