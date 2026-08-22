<?php

declare(strict_types=1);

use Graft\Ai\Tools\GitHubGetIssueTool;
use Graft\Ai\Tools\GitStatusTool;
use Graft\Data\Git\Status;
use Graft\Data\Platform\Issue;
use Graft\Facades\Git;
use Graft\Facades\GitHub;
use Graft\Tests\TestCase;
use Laravel\Ai\Tools\Request;

uses(TestCase::class);

it('allows a repo_path that equals or sits inside an allowlisted root', function () {
    $root = sys_get_temp_dir().'/graft-ai-allow-'.uniqid();
    $nested = $root.'/nested';
    mkdir($nested, 0777, true);

    config([
        'graft.ai.allowed_repos' => [$root],
        'graft.ai.allow_base_path' => false,
    ]);

    $fake = Git::fake();
    $fake->shouldReturn('status', new Status(staged: [], unstaged: [], untracked: []));

    $output = (new GitStatusTool)->handle(new Request(['repo_path' => $nested]));
    $data = json_decode($output, true);

    expect($data['is_clean'])->toBeTrue();
    $fake->assertCalled('status', fn ($args) => $args[0] === realpath($nested));

    @unlink($nested);
    @rmdir($nested);
    @rmdir($root);
});

it('denies a repo_path outside the allowlist', function () {
    $allowed = sys_get_temp_dir().'/graft-ai-root-'.uniqid();
    mkdir($allowed, 0777, true);

    config([
        'graft.ai.allowed_repos' => [$allowed],
        'graft.ai.allow_base_path' => false,
    ]);

    $fake = Git::fake();
    $output = (new GitStatusTool)->handle(new Request(['repo_path' => sys_get_temp_dir()]));

    expect(decodeToolError($output)['message'])->toContain('not allowlisted');
    $fake->assertNotCalled('status');

    @rmdir($allowed);
});

it('defaults an empty repo_path to base_path when the allowlist is empty', function () {
    config([
        'graft.ai.allowed_repos' => [],
        'graft.ai.allow_base_path' => true,
    ]);

    $fake = Git::fake();
    $fake->shouldReturn('status', new Status(staged: [], unstaged: [], untracked: []));

    $output = (new GitStatusTool)->handle(new Request([]));
    $data = json_decode($output, true);

    expect($data['is_clean'])->toBeTrue();
    $fake->assertCalled('status', fn ($args) => $args[0] === realpath(base_path()));
});

it('denies all repo_path tools when the allowlist is empty and allow_base_path is false', function () {
    config([
        'graft.ai.allowed_repos' => [],
        'graft.ai.allow_base_path' => false,
    ]);

    $fake = Git::fake();

    $emptyPath = (new GitStatusTool)->handle(new Request([]));
    $basePath = (new GitStatusTool)->handle(new Request(['repo_path' => base_path()]));

    expect(decodeToolError($emptyPath)['message'])->toContain('not allowlisted');
    expect(decodeToolError($basePath)['message'])->toContain('not allowlisted');
    $fake->assertNotCalled('status');
});

it('allows any GitHub repository when allowed_repositories is empty', function () {
    config(['graft.ai.allowed_repositories' => []]);

    $fake = GitHub::fake();
    $fake->shouldReturn('getIssue', new Issue(
        number: 1,
        title: 'Open',
        body: '',
        state: 'open',
        url: 'https://github.com/other/repo/issues/1',
        author: 'alice',
    ));

    $output = (new GitHubGetIssueTool)->handle(new Request([
        'repo' => 'other/repo',
        'number' => 1,
    ]));

    $data = json_decode($output, true);
    expect($data['number'])->toBe(1);
    $fake->assertCalled('getIssue');
});

it('rejects GitHub repositories that are not on the owner/repo allowlist', function () {
    config(['graft.ai.allowed_repositories' => ['Acme/App']]);

    $fake = GitHub::fake();
    $denied = (new GitHubGetIssueTool)->handle(new Request([
        'repo' => 'other/repo',
        'number' => 1,
    ]));
    $allowed = (new GitHubGetIssueTool)->handle(new Request([
        'repo' => 'acme/app',
        'number' => 1,
    ]));

    expect(decodeToolError($denied)['message'])->toContain('not allowlisted');
    expect(json_decode($allowed, true)['number'])->toBe(1);
    $fake->assertCalledTimes('getIssue', 1);
    $fake->assertCalled('getIssue', fn ($args) => $args[0] === 'acme/app');
});
