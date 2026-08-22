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

class GitHubCreatePrTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:github:create-pr';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Create a GitHub pull request.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repo = (string) $request->string('repo');
            AllowedRepository::assertAllowed($repo);

            $title = (string) $request->string('title');
            $body = (string) $request->string('body');
            $head = (string) $request->string('head');
            $base = (string) $request->string('base');

            if ($title === '' || $head === '' || $base === '') {
                return ToolResponse::error('Title, head, and base are required to create a pull request.');
            }

            /** @var bool $draft */
            $draft = $request->boolean('draft', false);

            $pr = GitHub::createPullRequest($repo, $title, $body, $head, $base, $draft);

            return ToolResponse::json([
                'number' => $pr->number,
                'title' => $pr->title,
                'state' => $pr->state,
                'head' => $pr->head,
                'base' => $pr->base,
                'draft' => $pr->draft,
                'url' => $pr->url,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error creating pull request: {$e->getMessage()}");
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
                ->description('The pull request title.')
                ->required(),
            'body' => $schema
                ->string()
                ->description('The pull request body/description.'),
            'head' => $schema
                ->string()
                ->description('The head branch to merge from.')
                ->required(),
            'base' => $schema
                ->string()
                ->description('The base branch to merge into.')
                ->required(),
            'draft' => $schema
                ->boolean()
                ->description('Create the pull request as a draft (default: false).'),
        ];
    }
}
