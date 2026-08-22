<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Graft\Ai\Tools\GitHubCreatePrTool;
use Graft\Data\Platform\PullRequest;
use Graft\Facades\GitHub;
use Graft\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new GitHubCreatePrTool;
});

it('returns the documented tool id', function () {
    expect(GitHubCreatePrTool::toolId())->toBe('graft:github:create-pr');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo, title, body, head, base, and draft fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'title', 'body', 'head', 'base', 'draft']);
});

it('creates a pull request and returns formatted data', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('createPullRequest', new PullRequest(
        number: 15,
        title: 'Add feature',
        body: 'Details',
        state: 'open',
        head: 'feature/x',
        base: 'main',
        url: 'https://github.com/owner/repo/pull/15',
        author: 'alice',
        draft: true,
        mergeable: true,
        createdAt: CarbonImmutable::now(),
    ));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'title' => 'Add feature',
        'body' => 'Details',
        'head' => 'feature/x',
        'base' => 'main',
        'draft' => true,
    ]));

    $data = json_decode($output, true);
    expect($data['number'])->toBe(15)
        ->and($data['title'])->toBe('Add feature')
        ->and($data['head'])->toBe('feature/x')
        ->and($data['base'])->toBe('main')
        ->and($data['draft'])->toBeTrue()
        ->and($data['url'])->toBe('https://github.com/owner/repo/pull/15');

    $fake->assertCalled('createPullRequest', function ($args) {
        return $args === ['owner/repo', 'Add feature', 'Details', 'feature/x', 'main', true];
    });
});

it('returns a structured error when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('createPullRequest', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'title' => 'Add feature',
        'body' => 'Details',
        'head' => 'feature/x',
        'base' => 'main',
    ]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
