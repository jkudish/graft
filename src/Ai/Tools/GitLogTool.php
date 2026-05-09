<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Data\Git\Commit;
use Graft\Facades\Git;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitLogTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:git:log';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get the git commit log for a repository.';
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

        /** @var int $limit */
        $limit = $request->integer('limit', 10) ?: 10;

        try {
            $commits = Git::log($repoPath, $limit);

            if ($commits->isEmpty()) {
                return 'No commits found.';
            }

            $formatted = $commits->map(fn (Commit $commit) => [
                'hash' => $commit->shortHash,
                'message' => $commit->message,
                'author' => $commit->author,
                'date' => $commit->date->toIso8601String(),
            ])->all();

            $data = [
                'count' => count($formatted),
                'commits' => $formatted,
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error getting git log: {$e->getMessage()}";
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
            'limit' => $schema
                ->integer()
                ->description('Maximum number of commits to return (default: 10).'),
        ];
    }
}
