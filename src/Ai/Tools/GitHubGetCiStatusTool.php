<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\AllowedRepository;
use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Ai\ToolResponse;
use Graft\Data\Platform\CheckRun;
use Graft\Facades\GitHub;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitHubGetCiStatusTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:github:get-ci-status';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get CI status for a git ref or pull request number.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $repo = (string) $request->string('repo');
            AllowedRepository::assertAllowed($repo);

            $ref = (string) $request->string('ref');
            $number = $request->integer('number');

            if ($number > 0) {
                $pr = GitHub::getPullRequest($repo, $number);
                $ref = $pr->head;
            }

            if ($ref === '') {
                return ToolResponse::error('A ref or pull request number is required.');
            }

            $status = GitHub::getCiStatus($repo, $ref);

            return ToolResponse::json([
                'ref' => $ref,
                'state' => $status->state,
                'check_runs' => $status->checkRuns->map(fn (CheckRun $run) => [
                    'id' => $run->id,
                    'name' => $run->name,
                    'status' => $run->status,
                    'conclusion' => $run->conclusion,
                    'url' => $run->url,
                ])->all(),
            ]);
        } catch (Throwable $e) {
            return ToolResponse::error("Error getting CI status: {$e->getMessage()}");
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
            'ref' => $schema
                ->string()
                ->description('Git ref (SHA, branch, or tag) to inspect.'),
            'number' => $schema
                ->integer()
                ->description('Pull request number whose head ref should be inspected.'),
        ];
    }
}
