<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Graft\Ai\Tools\GitHubGetCiStatusTool;
use Graft\Data\Platform\CheckRun;
use Graft\Data\Platform\CiStatus;
use Graft\Data\Platform\PullRequest;
use Graft\Facades\GitHub;
use Graft\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new GitHubGetCiStatusTool;
});

it('returns the documented tool id', function () {
    expect(GitHubGetCiStatusTool::toolId())->toBe('graft:github:get-ci-status');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo, ref, and number fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'ref', 'number']);
});

it('returns CI status for a git ref', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('getCiStatus', new CiStatus(
        state: 'success',
        checkRuns: collect([
            new CheckRun(id: 1, name: 'tests', status: 'completed', conclusion: 'success', url: 'https://example.test/1'),
        ]),
    ));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'ref' => 'abc123',
    ]));

    $data = json_decode($output, true);
    expect($data['ref'])->toBe('abc123')
        ->and($data['state'])->toBe('success')
        ->and($data['check_runs'][0]['name'])->toBe('tests')
        ->and($data['check_runs'][0]['conclusion'])->toBe('success');

    $fake->assertCalled('getCiStatus', fn ($args) => $args === ['owner/repo', 'abc123']);
    $fake->assertNotCalled('getPullRequest');
});

it('resolves a pull request number to its head ref', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('getPullRequest', new PullRequest(
        number: 42,
        title: 'Add feature',
        body: '',
        state: 'open',
        head: 'feature/ci',
        base: 'main',
        url: 'https://github.com/owner/repo/pull/42',
        author: 'alice',
        draft: false,
        mergeable: true,
        createdAt: CarbonImmutable::now(),
    ));
    $fake->shouldReturn('getCiStatus', new CiStatus(
        state: 'pending',
        checkRuns: collect(),
    ));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 42,
    ]));

    $data = json_decode($output, true);
    expect($data['ref'])->toBe('feature/ci')
        ->and($data['state'])->toBe('pending');

    $fake->assertCalled('getPullRequest', fn ($args) => $args === ['owner/repo', 42]);
    $fake->assertCalled('getCiStatus', fn ($args) => $args === ['owner/repo', 'feature/ci']);
});

it('returns a structured error when neither ref nor number is provided', function () {
    GitHub::fake();

    $output = $this->tool->handle(new Request(['repo' => 'owner/repo']));

    expect(decodeToolError($output)['message'])->toContain('ref or pull request number');
});

it('returns a structured error when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('getCiStatus', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'ref' => 'main',
    ]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
