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

                /*
                 * Auto-configure git credential auth on repos Graft creates
                 * (Git::init, Git::clone, Git::addWorktree).
                 *
                 * Modes:
                 *  - "baked" (default): the token is written into the repo's
                 *    .git/config credential helper. Works under config:cache
                 *    where $_ENV is empty. Token is at-rest on disk inside
                 *    .git/config — same threat surface as .env.
                 *  - "env": the helper reads ${GRAFT_GITHUB_TOKEN} at lookup
                 *    time. Graft passes that var to every git subprocess
                 *    explicitly, so it works under config:cache too — no
                 *    token at rest, but the token must be reachable at the
                 *    moment a git command runs.
                 *
                 * `host` defaults to deriving the credential host from
                 * `base_url` (e.g. https://api.github.com → https://github.com),
                 * which works for github.com and most GH Enterprise installs.
                 */
                'git_credentials' => [
                    'enabled' => env('GRAFT_GIT_CREDENTIALS_ENABLED', true),
                    'mode' => env('GRAFT_GIT_CREDENTIALS_MODE', 'baked'),
                    'username' => env('GRAFT_GIT_CREDENTIALS_USERNAME', 'x-access-token'),
                    'host' => env('GRAFT_GIT_CREDENTIALS_HOST'),
                ],
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

    /*
    |--------------------------------------------------------------------------
    | AI Tools
    |--------------------------------------------------------------------------
    |
    | repo_path is resolved with realpath and must equal or sit inside an
    | allowlisted root. An empty allowed_repos list with allow_base_path
    | enabled (the default) permits only base_path(). Set allow_base_path
    | to false to fail closed when no roots are configured.
    |
    | allowed_repositories optionally restricts GitHub owner/repo tools.
    | An empty list allows every repository (backward compatible).
    |
    */

    'ai' => [
        'allowed_repos' => array_values(array_filter(array_map('trim', explode(',', (string) env('GRAFT_AI_ALLOWED_REPOS', ''))))),
        'allow_base_path' => env('GRAFT_AI_ALLOW_BASE_PATH', true), // backward compatible default
        'allowed_repositories' => array_values(array_filter(array_map('trim', explode(',', (string) env('GRAFT_AI_ALLOWED_REPOSITORIES', ''))))), // owner/repo; empty = allow all
    ],

];
