<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\AllowedPath;
use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Ai\ToolResponse;
use Graft\Facades\Git;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitCheckoutTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:git:checkout';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Check out a git branch, optionally creating it.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repoPath = AllowedPath::resolve((string) $request->string('repo_path'));
            $branch = (string) $request->string('branch');

            if ($branch === '') {
                return ToolResponse::error('A branch name is required.');
            }

            /** @var bool $create */
            $create = $request->boolean('create', false);

            Git::checkout($repoPath, $branch, $create);

            return ToolResponse::json([
                'ok' => true,
                'branch' => $branch,
                'created' => $create,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error checking out branch: {$e->getMessage()}");
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
            'repo_path' => $schema
                ->string()
                ->description('Path to the git repository (defaults to project root).'),
            'branch' => $schema
                ->string()
                ->description('The branch to check out.')
                ->required(),
            'create' => $schema
                ->boolean()
                ->description('Create the branch if it does not exist (default: false).'),
        ];
    }
}
