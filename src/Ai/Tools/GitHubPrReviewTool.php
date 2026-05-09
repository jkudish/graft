<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use Graft\Ai\Contracts\IdentifiableTool;
use Graft\Facades\GitHub;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitHubPrReviewTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:github:pr-review';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get details of a GitHub pull request for review.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        /** @var string $repo */
        $repo = (string) $request->string('repo');
        /** @var int $number */
        $number = $request->integer('number');

        try {
            $pr = GitHub::getPullRequest($repo, $number);

            $data = [
                'number' => $pr->number,
                'title' => $pr->title,
                'body' => $pr->body,
                'state' => $pr->state,
                'head' => $pr->head,
                'base' => $pr->base,
                'author' => $pr->author,
                'draft' => $pr->draft,
                'mergeable' => $pr->mergeable,
                'labels' => $pr->labels,
                'reviewers' => $pr->reviewers,
                'url' => $pr->url,
                'created_at' => $pr->createdAt?->toIso8601String(),
                'updated_at' => $pr->updatedAt?->toIso8601String(),
                'merged_at' => $pr->mergedAt?->toIso8601String(),
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error fetching PR #{$number}: {$e->getMessage()}";
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
        ];
    }
}
