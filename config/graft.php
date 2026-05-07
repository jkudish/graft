<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Git Binary
    |--------------------------------------------------------------------------
    */

    'git_binary' => env('GRAFT_GIT_BINARY', 'git'),

    /*
    |--------------------------------------------------------------------------
    | Process Timeout
    |--------------------------------------------------------------------------
    |
    | Default timeout in seconds for git commands. Long operations
    | like clone may override this per-call.
    |
    */

    'timeout' => env('GRAFT_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Platform Providers
    |--------------------------------------------------------------------------
    */

    'platform' => [
        'default' => env('GRAFT_PLATFORM', 'github'),

        'providers' => [
            'github' => [
                'token' => env('GITHUB_TOKEN'),
                'base_url' => env('GITHUB_API_URL', 'https://api.github.com'),
            ],
            // 'gitlab' => [
            //     'token' => env('GITLAB_TOKEN'),
            //     'base_url' => env('GITLAB_API_URL', 'https://gitlab.com/api/v4'),
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Testing
    |--------------------------------------------------------------------------
    */

    'testing' => [
        'fixture_repo' => env('GRAFT_TEST_REPO', 'jkudish/graft-test-fixture'),
    ],

];
