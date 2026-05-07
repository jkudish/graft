<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use App\Ai\Contracts\IdentifiableTool;
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
        /** @var string $repoPath */
        $repoPath = (string) $request->string('repo_path');
        if ($repoPath === '') {
            $repoPath = base_path();
        }

        /** @var bool $remote */
        $remote = $request->boolean('remote', false);

        try {
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

            $data = [
                'count' => count($formatted),
                'branches' => $formatted,
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error listing branches: {$e->getMessage()}";
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
