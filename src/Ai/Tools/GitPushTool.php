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

class GitPushTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:git:push';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Push a git branch to a remote.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repoPath = AllowedPath::resolve((string) $request->string('repo_path'));

            $remote = (string) $request->string('remote');
            $branch = (string) $request->string('branch');

            /** @var bool $force */
            $force = $request->boolean('force', false);
            /** @var bool $setUpstream */
            $setUpstream = $request->boolean('set_upstream', false);

            Git::push(
                $repoPath,
                $remote !== '' ? $remote : null,
                $branch !== '' ? $branch : null,
                $force,
                $setUpstream,
            );

            return ToolResponse::json([
                'ok' => true,
                'remote' => $remote !== '' ? $remote : null,
                'branch' => $branch !== '' ? $branch : null,
                'force' => $force,
                'set_upstream' => $setUpstream,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error pushing branch: {$e->getMessage()}");
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
                ->string()
                ->description('Remote name to push to (default: the configured upstream).'),
            'branch' => $schema
                ->string()
                ->description('Branch to push (default: the current branch).'),
            'set_upstream' => $schema
                ->boolean()
                ->description('Set the upstream tracking branch (default: false).'),
            'force' => $schema
                ->boolean()
                ->description('Force-push the branch (default: false).'),
        ];
    }
}
