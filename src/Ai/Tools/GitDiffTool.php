<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use App\Ai\Contracts\IdentifiableTool;
use Graft\Facades\Git;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitDiffTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:git:diff';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get the git diff for a repository, optionally showing only staged changes.';
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

        /** @var bool $staged */
        $staged = $request->boolean('staged', false);

        try {
            $diff = Git::diff($repoPath, $staged);

            if ($diff === '') {
                return 'No differences found.';
            }

            return $diff;
        } catch (Throwable $e) {
            return "Error getting git diff: {$e->getMessage()}";
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
            'staged' => $schema
                ->boolean()
                ->description('Show only staged changes (default: false).'),
        ];
    }
}
