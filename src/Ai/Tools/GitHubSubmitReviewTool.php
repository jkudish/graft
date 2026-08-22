<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\AllowedRepository;
use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Ai\ToolResponse;
use Graft\Facades\GitHub;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitHubSubmitReviewTool implements IdentifiableTool, Tool
{
    private const EVENTS = ['APPROVE', 'REQUEST_CHANGES', 'COMMENT'];

    public static function toolId(): string
    {
        return 'graft:github:submit-review';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Submit a review on a GitHub pull request (approve, request changes, or comment).';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        /** @var int $number */
        $number = $request->integer('number');

        try {
            $repo = (string) $request->string('repo');
            AllowedRepository::assertAllowed($repo);

            if ($number < 1) {
                return ToolResponse::error('A pull request number is required.');
            }

            $body = (string) $request->string('body');
            $event = strtoupper((string) $request->string('event'));
            if ($event === '') {
                $event = 'COMMENT';
            }

            if (! in_array($event, self::EVENTS, true)) {
                return ToolResponse::error('Review event must be APPROVE, REQUEST_CHANGES, or COMMENT.');
            }

            $commitId = (string) $request->string('commit_id');
            $comments = self::normalizeComments($request->array('comments'));

            $response = GitHub::submitReview(
                $repo,
                $number,
                $body,
                $event,
                $comments,
                $commitId !== '' ? $commitId : null,
            );

            return ToolResponse::json([
                'ok' => true,
                'number' => $number,
                'event' => $event,
                'review' => $response,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error submitting review on PR #{$number}: {$e->getMessage()}");
        }
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema
                ->string()
                ->description('The repository in owner/repo format.')
                ->required(),
            'number' => $schema
                ->integer()
                ->description('The pull request number.')
                ->required(),
            'body' => $schema
                ->string()
                ->description('The review body text.'),
            'event' => $schema
                ->string()
                ->description('Review event: APPROVE, REQUEST_CHANGES, or COMMENT (default: COMMENT).'),
            'comments' => $schema
                ->array()
                ->items($schema->object([
                    'path' => $schema->string()->description('File path for the inline comment.')->required(),
                    'line' => $schema->integer()->description('Line number for the inline comment.')->required(),
                    'body' => $schema->string()->description('Inline comment text.')->required(),
                ]))
                ->description('Optional inline review comments.'),
            'commit_id' => $schema
                ->string()
                ->description('Commit SHA the review applies to (optional).'),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $comments
     * @return array<int, array{path: string, line: int, body: string}>
     */
    private static function normalizeComments(array $comments): array
    {
        $normalized = [];

        foreach ($comments as $comment) {
            if (! is_array($comment)) {
                continue;
            }

            $path = trim((string) ($comment['path'] ?? ''));
            $body = (string) ($comment['body'] ?? '');
            $line = (int) ($comment['line'] ?? 0);

            if ($path === '' || $line < 1) {
                continue;
            }

            $normalized[] = [
                'path' => $path,
                'line' => $line,
                'body' => $body,
            ];
        }

        return $normalized;
    }
}
