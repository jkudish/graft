<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Graft\Ai\Tools\GitHubGetIssueTool;
use Graft\Data\Platform\Issue;
use Graft\Facades\GitHub;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitHubGetIssueTool;
});

it('returns the documented tool id', function () {
    expect(GitHubGetIssueTool::toolId())->toBe('graft:github:get-issue');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo and number fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'number']);
});

it('returns formatted issue data on success', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('getIssue', new Issue(
        number: 7,
        title: 'Bug report',
        body: 'It broke',
        state: 'open',
        url: 'https://github.com/owner/repo/issues/7',
        author: 'alice',
        labels: ['bug'],
        assignees: ['bob'],
        createdAt: CarbonImmutable::parse('2026-05-01T12:00:00Z'),
    ));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo', 'number' => 7]));

    $data = json_decode($output, true);
    expect($data['number'])->toBe(7)
        ->and($data['title'])->toBe('Bug report')
        ->and($data['labels'])->toBe(['bug'])
        ->and($data['assignees'])->toBe(['bob']);

    $fake->assertCalled('getIssue', fn ($args) => $args[0] === 'owner/repo' && $args[1] === 7);
});

it('returns an Error message when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('getIssue', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo', 'number' => 7]));

    expect($output)->toStartWith('Error');
});
