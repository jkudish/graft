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

class GitStatusTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:git:status';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get the git status of a repository, showing staged, unstaged, and untracked files.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repoPath = AllowedPath::resolve((string) $request->string('repo_path'));
            $status = Git::status($repoPath);

            return ToolResponse::json([
                'is_clean' => $status->isClean(),
                'staged' => $status->staged,
                'unstaged' => $status->unstaged,
                'untracked' => $status->untracked,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error getting git status: {$e->getMessage()}");
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
        ];
    }
}
