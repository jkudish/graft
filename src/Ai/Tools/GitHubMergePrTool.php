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

class GitHubMergePrTool implements IdentifiableTool, Tool
{
    private const METHODS = ['squash', 'merge', 'rebase'];

    public static function toolId(): string
    {
        return 'graft:github:merge-pr';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Merge a GitHub pull request using squash, merge, or rebase.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        /** @var int $number */
        $number = $request->integer('number');

        try {
            $repo = (string) $request->string('repo');
            AllowedRepository::assertAllowed($repo);

            if ($number < 1) {
                return ToolResponse::error('A pull request number is required.');
            }

            $method = strtolower((string) $request->string('method'));
            if ($method === '') {
                $method = 'squash';
            }

            if (! in_array($method, self::METHODS, true)) {
                return ToolResponse::error('Merge method must be squash, merge, or rebase.');
            }

            GitHub::mergePullRequest($repo, $number, $method);

            return ToolResponse::json([
                'ok' => true,
                'number' => $number,
                'method' => $method,
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error merging PR #{$number}: {$e->getMessage()}");
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
            'number' => $schema
                ->integer()
                ->description('The pull request number.')
                ->required(),
            'method' => $schema
                ->string()
                ->description('Merge method: squash, merge, or rebase (default: squash).'),
        ];
    }
}
