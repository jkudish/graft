<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Graft\Ai\Tools\GitHubListPrsTool;
use Graft\Data\Platform\PullRequest;
use Graft\Facades\GitHub;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitHubListPrsTool;
});

it('returns the documented tool id', function () {
    expect(GitHubListPrsTool::toolId())->toBe('graft:github:list-prs');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo and state fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'state']);
});

it('returns formatted PR data on success', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('listPullRequests', collect([
        new PullRequest(
            number: 42,
            title: 'Add feature',
            body: 'Body',
            state: 'open',
            head: 'feature/foo',
            base: 'main',
            url: 'https://github.com/owner/repo/pull/42',
            author: 'alice',
            draft: false,
            mergeable: true,
            createdAt: CarbonImmutable::now(),
        ),
    ]));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo', 'state' => 'open']));

    $data = json_decode($output, true);
    expect($data['count'])->toBe(1)
        ->and($data['pull_requests'][0]['number'])->toBe(42)
        ->and($data['pull_requests'][0]['title'])->toBe('Add feature')
        ->and($data['pull_requests'][0]['author'])->toBe('alice')
        ->and($data['pull_requests'][0]['url'])->toBe('https://github.com/owner/repo/pull/42');

    $fake->assertCalled('listPullRequests', fn ($args) => $args[0] === 'owner/repo' && $args[1] === 'open');
});

it('returns a "no PRs" message when collection is empty', function () {
    GitHub::fake()->shouldReturn('listPullRequests', collect());

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo']));

    expect($output)->toContain('No open pull requests found for owner/repo');
});

it('returns an Error message when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('listPullRequests', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo']));

    expect($output)->toStartWith('Error');
});
