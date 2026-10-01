<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitHubListPrsTool;
use Graft\Ai\Tools\GitLogTool;
use Graft\Ai\Tools\GitStatusTool;
use Graft\Data\Git\Status;
use Graft\Facades\Git;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\ToolCall;

it('invokes a registered Graft tool through the SDK generation loop', function () {
    $this->app->register(AiServiceProvider::class);

    $fake = Git::fake();
    $fake->shouldReturn('status', new Status(
        staged: ['src/Changed.php'],
        unstaged: [],
        untracked: ['notes.txt'],
    ));

    $agent = new class implements Agent, HasTools
    {
        use Promptable;

        public function instructions(): string
        {
            return 'Inspect the repository using the registered Graft tools.';
        }

        public function tools(): array
        {
            return [new GitLogTool, new GitStatusTool, new GitHubListPrsTool];
        }
    };

    $agent::fake([
        new ToolCall('call_status', 'GitStatusTool', ['repo_path' => '/tmp/release-repo']),
        'The repository has staged and untracked files.',
    ])->preventStrayPrompts();

    $response = $agent->prompt('Check the release repository.', provider: 'openai', model: 'gpt-4.1-mini');

    $fake->assertCalledTimes('status', 1);
    $fake->assertCalled('status', fn ($args) => $args[0] === '/tmp/release-repo');

    expect($response->text)->toBe('The repository has staged and untracked files.')
        ->and($response->toolResults)->toHaveCount(1);

    $result = $response->toolResults->first();

    expect($result->id)->toBe('call_status')
        ->and($result->successful())->toBeTrue()
        ->and(json_decode($result->result, true))->toBe([
            'is_clean' => false,
            'staged' => ['src/Changed.php'],
            'unstaged' => [],
            'untracked' => ['notes.txt'],
        ]);
});
