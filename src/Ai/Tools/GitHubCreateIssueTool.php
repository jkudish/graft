<?php

declare(strict_types=1);

namespace Graft\Ai\Tools;

use App\Ai\Contracts\IdentifiableTool;
use Graft\Facades\GitHub;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class GitHubCreateIssueTool implements IdentifiableTool, Tool
{
    public static function toolId(): string
    {
        return 'graft:github:create-issue';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Create a new GitHub issue in a repository.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        /** @var string $repo */
        $repo = (string) $request->string('repo');
        /** @var string $title */
        $title = (string) $request->string('title');
        /** @var string $body */
        $body = (string) $request->string('body');

        /** @var string $labelsJson */
        $labelsJson = (string) $request->string('labels');

        /** @var list<string> $labels */
        $labels = [];
        if ($labelsJson !== '') {
            $decoded = json_decode($labelsJson, true);
            if (is_array($decoded)) {
                /** @var list<string> $labels */
                $labels = $decoded;
            }
        }

        try {
            $issue = GitHub::createIssue($repo, $title, $body, $labels);

            $data = [
                'number' => $issue->number,
                'title' => $issue->title,
                'state' => $issue->state,
                'url' => $issue->url,
                'labels' => $issue->labels,
            ];

            return json_encode($data, JSON_PRETTY_PRINT) ?: 'No data.';
        } catch (Throwable $e) {
            return "Error creating issue: {$e->getMessage()}";
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
            'title' => $schema
                ->string()
                ->description('The issue title.')
                ->required(),
            'body' => $schema
                ->string()
                ->description('The issue body/description.')
                ->required(),
            'labels' => $schema
                ->string()
                ->description('JSON array of label names to apply (optional).'),
        ];
    }
}
