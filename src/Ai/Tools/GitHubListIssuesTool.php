<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Data\Platform\Issue;
use Graft\Facades\GitHub;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitHubListIssuesTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:github:list-issues';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'List GitHub issues for a repository.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        /** @var string $repo */
        $repo = (string) $request->string('repo');
        /** @var string $state */
        $state = (string) $request->string('state');
        if ($state === '') {
            $state = 'open';
        }

        try {
            $issues = GitHub::listIssues($repo, $state);

            if ($issues->isEmpty()) {
                return "No {$state} issues found for {$repo}.";
            }

            $formatted = $issues->map(fn (Issue $issue) => [
                'number' => $issue->number,
                'title' => $issue->title,
                'state' => $issue->state,
                'author' => $issue->author,
                'labels' => $issue->labels,
                'url' => $issue->url,
            ])->all();

            $data = [
                'count' => count($formatted),
                'issues' => $formatted,
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error listing issues: {$e->getMessage()}";
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
            'state' => $schema
                ->string()
                ->description('Issue state filter: open, closed, or all (default: open).'),
        ];
    }
}
