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

class GitCommitTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:git:commit';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Stage paths and create a git commit.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repoPath = AllowedPath::resolve((string) $request->string('repo_path'));
            $message = (string) $request->string('message');

            if ($message === '') {
                return ToolResponse::error('A commit message is required.');
            }

            $paths = $request->array('paths');
            $paths = $paths === [] ? '.' : array_values(array_map(
                static fn (mixed $path): string => (string) $path,
                $paths,
            ));

            /** @var bool $allowEmpty */
            $allowEmpty = $request->boolean('allow_empty', false);

            Git::add($repoPath, $paths);
            $commit = Git::commit($repoPath, $message, $allowEmpty);

            return ToolResponse::json([
                'hash' => $commit->shortHash,
                'message' => $commit->message,
                'author' => $commit->author,
                'date' => $commit->date->toIso8601String(),
                'allow_empty' => $allowEmpty,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error creating commit: {$e->getMessage()}");
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
            'message' => $schema
                ->string()
                ->description('The commit message.')
                ->required(),
            'paths' => $schema
                ->array()
                ->items($schema->string())
                ->description('Paths to stage before committing (default: all files).'),
            'allow_empty' => $schema
                ->boolean()
                ->description('Allow creating an empty commit (default: false).'),
        ];
    }
}
