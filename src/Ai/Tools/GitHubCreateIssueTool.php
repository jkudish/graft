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

class GitHubCreateIssueTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:github:create-issue';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Create a new GitHub issue in a repository.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repo = (string) $request->string('repo');
            AllowedRepository::assertAllowed($repo);

            /** @var string $title */
            $title = (string) $request->string('title');
            /** @var string $body */
            $body = (string) $request->string('body');

            /** @var list<string> $labels */
            $labels = array_values(array_filter(
                array_map(static fn (mixed $label): string => trim((string) $label), $request->array('labels')),
                static fn (string $label): bool => $label !== '',
            ));

            $issue = GitHub::createIssue($repo, $title, $body, $labels);

            return ToolResponse::json([
                'number' => $issue->number,
                'title' => $issue->title,
                'state' => $issue->state,
                'url' => $issue->url,
                'labels' => $issue->labels,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error creating issue: {$e->getMessage()}");
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
            'title' => $schema
                ->string()
                ->description('The issue title.')
                ->required(),
            'body' => $schema
                ->string()
                ->description('The issue body/description.')
                ->required(),
            'labels' => $schema
                ->array()
                ->items($schema->string())
                ->description('Label names to apply (optional).'),
        ];
    }
}
