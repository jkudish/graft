<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitHubListIssuesTool;
use Graft\Data\Platform\Issue;
use Graft\Facades\GitHub;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitHubListIssuesTool;
});

it('returns the documented tool id', function () {
    expect(GitHubListIssuesTool::toolId())->toBe('graft:github:list-issues');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo and state fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'state']);
});

it('returns formatted issues data on success', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('listIssues', collect([
        new Issue(
            number: 1,
            title: 'First',
            body: '',
            state: 'open',
            url: 'https://github.com/owner/repo/issues/1',
            author: 'alice',
            labels: ['bug'],
        ),
        new Issue(
            number: 2,
            title: 'Second',
            body: '',
            state: 'open',
            url: 'https://github.com/owner/repo/issues/2',
            author: 'bob',
        ),
    ]));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo', 'state' => 'open']));

    $data = json_decode($output, true);
    expect($data['count'])->toBe(2)
        ->and($data['issues'][0]['number'])->toBe(1)
        ->and($data['issues'][0]['labels'])->toBe(['bug'])
        ->and($data['issues'][1]['author'])->toBe('bob');

    $fake->assertCalled('listIssues', fn ($args) => $args[0] === 'owner/repo' && $args[1] === 'open');
});

it('returns a "no issues" message when collection is empty', function () {
    GitHub::fake()->shouldReturn('listIssues', collect());

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo']));

    expect($output)->toContain('No open issues found for owner/repo');
});

it('returns an Error message when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('listIssues', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo']));

    expect($output)->toStartWith('Error');
});
