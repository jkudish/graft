<?php

use Graft\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit', 'Integration');

pest()->tia()->always()->locally();

/**
 * Allowlist a real directory so git AI tools can resolve repo_path in tests.
 */
function allowlistedRepoPath(): string
{
    $path = realpath(sys_get_temp_dir());

    if ($path === false) {
        throw new RuntimeException('Unable to resolve the system temp directory.');
    }

    config(['graft.ai.allowed_repos' => [$path]]);

    return $path;
}

/**
 * @return array{error: bool, message: string}
 */
function decodeToolError(string $output): array
{
    $data = json_decode($output, true);

    expect($data)->toBeArray()
        ->and($data['error'])->toBeTrue()
        ->and($data['message'])->toBeString();

    return $data;
}
