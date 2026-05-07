<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use App\Ai\Contracts\IdentifiableTool;
use Graft\Facades\GitHub;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitHubGetIssueTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:github:get-issue';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get details of a GitHub issue by number.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        /** @var string $repo */
        $repo = (string) $request->string('repo');
        /** @var int $number */
        $number = $request->integer('number');

        try {
            $issue = GitHub::getIssue($repo, $number);

            $data = [
                'number' => $issue->number,
                'title' => $issue->title,
                'body' => $issue->body,
                'state' => $issue->state,
                'url' => $issue->url,
                'author' => $issue->author,
                'labels' => $issue->labels,
                'assignees' => $issue->assignees,
                'created_at' => $issue->createdAt?->toIso8601String(),
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error fetching issue #{$number}: {$e->getMessage()}";
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
                ->description('The issue number.')
                ->required(),
        ];
    }
}
