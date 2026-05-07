<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use App\Ai\Contracts\IdentifiableTool;
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
        /** @var string $repoPath */
        $repoPath = (string) $request->string('repo_path');
        if ($repoPath === '') {
            $repoPath = base_path();
        }

        try {
            $status = Git::status($repoPath);

            $data = [
                'is_clean' => $status->isClean(),
                'staged' => $status->staged,
                'unstaged' => $status->unstaged,
                'untracked' => $status->untracked,
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error getting git status: {$e->getMessage()}";
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
