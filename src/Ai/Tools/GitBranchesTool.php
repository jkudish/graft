<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\AllowedPath;
use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Ai\ToolResponse;
use Graft\Data\Git\Branch;
use Graft\Facades\Git;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitBranchesTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:git:branches';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'List branches in a git repository.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repoPath = AllowedPath::resolve((string) $request->string('repo_path'));

            /** @var bool $remote */
            $remote = $request->boolean('remote', false);

            $branches = Git::branches($repoPath, $remote);

            if ($branches->isEmpty()) {
                return 'No branches found.';
            }

            $formatted = $branches->map(fn (Branch $branch) => [
                'name' => $branch->name,
                'is_current' => $branch->isCurrent,
                'is_remote' => $branch->isRemote,
                'upstream' => $branch->upstream,
            ])->all();

            return ToolResponse::json([
                'count' => count($formatted),
                'branches' => $formatted,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error listing branches: {$e->getMessage()}");
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
            'remote' => $schema
                ->boolean()
                ->description('Include remote branches (default: false).'),
        ];
    }
}
