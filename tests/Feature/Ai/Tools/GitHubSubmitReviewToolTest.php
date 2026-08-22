<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitHubSubmitReviewTool;
use Graft\Facades\GitHub;
use Graft\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new GitHubSubmitReviewTool;
});

it('returns the documented tool id', function () {
    expect(GitHubSubmitReviewTool::toolId())->toBe('graft:github:submit-review');
});

it('returns a non-empty description', function () {
    expect($this->tool->description())->toBeString()->not->toBeEmpty();
});

it('exposes a schema with repo, number, body, event, comments, and commit_id fields', function () {
    $schema = $this->tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['repo', 'number', 'body', 'event', 'comments', 'commit_id']);
});

it('submits a review with the current GitHub signature', function () {
    $fake = GitHub::fake();
    $fake->shouldReturn('submitReview', [
        'id' => 99,
        'body' => 'Looks good',
        'event' => 'APPROVE',
    ]);

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 12,
        'body' => 'Looks good',
        'event' => 'APPROVE',
        'comments' => [
            ['path' => 'src/Foo.php', 'line' => 10, 'body' => 'Nice'],
        ],
        'commit_id' => 'abc123',
    ]));

    $data = json_decode($output, true);
    expect($data['ok'])->toBeTrue()
        ->and($data['number'])->toBe(12)
        ->and($data['event'])->toBe('APPROVE')
        ->and($data['review']['id'])->toBe(99);

    $fake->assertCalled('submitReview', function ($args) {
        return $args[0] === 'owner/repo'
            && $args[1] === 12
            && $args[2] === 'Looks good'
            && $args[3] === 'APPROVE'
            && $args[4] === [['path' => 'src/Foo.php', 'line' => 10, 'body' => 'Nice']]
            && $args[5] === 'abc123';
    });
});

it('defaults the review event to COMMENT', function () {
    $fake = GitHub::fake();

    $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 3,
        'body' => 'Nit',
    ]));

    $fake->assertCalled('submitReview', fn ($args) => $args[3] === 'COMMENT' && $args[4] === [] && $args[5] === null);
});

it('returns a structured error for an invalid review event', function () {
    $fake = GitHub::fake();

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 3,
        'event' => 'DISMISS',
    ]));

    expect(decodeToolError($output)['message'])->toContain('APPROVE, REQUEST_CHANGES, or COMMENT');
    $fake->assertNotCalled('submitReview');
});

it('returns a structured error when the underlying call throws', function () {
    GitHub::fake()->shouldThrow('submitReview', new RuntimeException('boom'));

    $output = $this->tool->handle(new Request([
        'repo' => 'owner/repo',
        'number' => 3,
        'body' => 'Nit',
    ]));

    expect(decodeToolError($output)['message'])->toContain('boom');
});
