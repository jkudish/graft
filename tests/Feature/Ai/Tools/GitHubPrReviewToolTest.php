<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Graft\Ai\Tools\GitHubPrReviewTool;
use Graft\Data\Platform\PullRequest;
use Graft\Facades\GitHub;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new GitHubPrReviewTool;
});

it('returns the documented tool id', function () {
    expect(GitHubPrReviewTool::toolId())->toBe('graft:github:pr-review');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo and number fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'number']);
});

it('returns formatted PR data on success', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('getPullRequest', new PullRequest(
        number: 12,
        title: 'Refactor module',
        body: 'Big body',
        state: 'open',
        head: 'feature/refactor',
        base: 'main',
        url: 'https://github.com/owner/repo/pull/12',
        author: 'alice',
        draft: false,
        mergeable: true,
        labels: ['refactor'],
        reviewers: ['bob'],
        createdAt: CarbonImmutable::parse('2026-05-01T12:00:00Z'),
        updatedAt: CarbonImmutable::parse('2026-05-02T12:00:00Z'),
    ));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo', 'number' => 12]));

    $data = json_decode($output, true);
    expect($data['number'])->toBe(12)
        ->and($data['title'])->toBe('Refactor module')
        ->and($data['head'])->toBe('feature/refactor')
        ->and($data['base'])->toBe('main')
        ->and($data['labels'])->toBe(['refactor'])
        ->and($data['reviewers'])->toBe(['bob'])
        ->and($data['merged_at'])->toBeNull();

    $fake->assertCalled('getPullRequest', fn ($args) => $args[0] === 'owner/repo' && $args[1] === 12);
});

it('returns an Error message when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('getPullRequest', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo', 'number' => 1]));

    expect($output)->toStartWith('Error');
});
