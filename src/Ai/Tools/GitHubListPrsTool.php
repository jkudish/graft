<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Data\Platform\PullRequest;
use Graft\Enums\Platform\ItemState;
use Graft\Facades\GitHub;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitHubListPrsTool implements IdentifiableTool, Tool
{
    #[\Override]
    public static function toolId(): string
    {
        return 'graft:github:list-prs';
    }

    /**
     * Get the description of the tool's purpose.
     */
    #[\Override]
    public function description(): string
    {
        return 'List pull requests for a GitHub repository.';
    }

    /**
     * Execute the tool.
     */
    #[\Override]
    public function handle(Request $request): string
    {
        /** @var string $repo */
        $repo = (string) $request->string('repo');
        $state = ItemState::tryFrom((string) $request->string('state')) ?? ItemState::Open;

        try {
            $prs = GitHub::listPullRequests($repo, $state);

            if ($prs->isEmpty()) {
                return "No {$state->value} pull requests found for {$repo}.";
            }

            $formatted = $prs->map(fn (PullRequest $pr) => [
                'number' => $pr->number,
                'title' => $pr->title,
                'state' => $pr->state->value,
                'author' => $pr->author,
                'head' => $pr->head,
                'base' => $pr->base,
                'draft' => $pr->draft,
                'url' => $pr->url,
            ])->all();

            $data = [
                'count' => count($formatted),
                'pull_requests' => $formatted,
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error listing pull requests: {$e->getMessage()}";
        }
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema
                ->string()
                ->description('The repository in owner/repo format.')
                ->required(),
            'state' => $schema
                ->string()
                ->description('PR state filter: open, closed, or all (default: open).'),
        ];
    }
}
