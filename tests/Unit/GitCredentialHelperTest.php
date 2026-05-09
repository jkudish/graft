<?php

declare(strict_types=1);

use Graft\Auth\GitCredentialHelper;
use Symfony\Component\Process\Process;

test('isEnabled is false when token is null', function () {
    $helper = new GitCredentialHelper(token: null);

    expect($helper->isEnabled())->toBeFalse();
});

test('isEnabled is false when token is empty string', function () {
    $helper = new GitCredentialHelper(token: '');

    expect($helper->isEnabled())->toBeFalse();
});

test('isEnabled is false when explicitly disabled', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test', enabled: false);

    expect($helper->isEnabled())->toBeFalse();
});

test('isEnabled is true with token in baked mode', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test');

    expect($helper->isEnabled())->toBeTrue()
        ->and($helper->mode())->toBe(GitCredentialHelper::MODE_BAKED);
});

test('isEnabled is true with token in env mode', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test', mode: GitCredentialHelper::MODE_ENV);

    expect($helper->isEnabled())->toBeTrue();
});

test('constructor rejects unknown mode', function () {
    new GitCredentialHelper(token: 'ghp_test', mode: 'invalid');
})->throws(InvalidArgumentException::class, "must be 'baked' or 'env'");

test('configKey defaults to derived github.com host', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test');

    expect($helper->configKey())->toBe('credential.https://github.com.helper');
});

test('configKey uses explicit host when provided', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_test',
        host: 'https://github.example.com',
    );

    expect($helper->configKey())->toBe('credential.https://github.example.com.helper');
});

test('configKey strips trailing slash from host', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_test',
        host: 'https://github.com/',
    );

    expect($helper->configKey())->toBe('credential.https://github.com.helper');
});

test('configKey derives host from GH Enterprise base url', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_test',
        apiBaseUrl: 'https://github.example.com/api/v3',
    );

    expect($helper->configKey())->toBe('credential.https://github.example.com.helper');
});

test('baked mode bakes literal token into helper', function () {
    $helper = new GitCredentialHelper(token: 'ghp_secret_token_123');

    expect($helper->configValue())
        ->toContain('username=x-access-token')
        ->toContain('password=ghp_secret_token_123');
});

test('env mode uses GRAFT_GITHUB_TOKEN placeholder', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_secret',
        mode: GitCredentialHelper::MODE_ENV,
    );

    expect($helper->configValue())
        ->toContain('username=x-access-token')
        ->toContain('password=${GRAFT_GITHUB_TOKEN}')
        ->not->toContain('ghp_secret');
});

test('configValue is a shell-runnable credential helper', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test');

    expect($helper->configValue())
        ->toStartWith('!')
        ->toContain('echo')
        ->toContain('username=')
        ->toContain('password=');
});

test('custom username is honored', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_test',
        username: 'custom-bot',
    );

    expect($helper->configValue())->toContain('username=custom-bot');
});

test('shell metacharacters in token are escaped', function () {
    $helper = new GitCredentialHelper(token: 'tok"with$danger');

    // Inside the double-quoted shell string, " $ ` and \ must be escaped.
    expect($helper->configValue())
        ->toContain('\\"')
        ->toContain('\\$');
});

test('processEnv is empty in baked mode', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test');

    expect($helper->processEnv())->toBe([]);
});

test('processEnv returns GRAFT_GITHUB_TOKEN in env mode', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_secret',
        mode: GitCredentialHelper::MODE_ENV,
    );

    expect($helper->processEnv())->toBe([
        'GRAFT_GITHUB_TOKEN' => 'ghp_secret',
    ]);
});

test('processEnv is empty when disabled', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_secret',
        enabled: false,
        mode: GitCredentialHelper::MODE_ENV,
    );

    expect($helper->processEnv())->toBe([]);
});

test('processEnv is empty when token is null', function () {
    $helper = new GitCredentialHelper(
        token: null,
        mode: GitCredentialHelper::MODE_ENV,
    );

    expect($helper->processEnv())->toBe([]);
});

test('deriveHostFromApiUrl strips api prefix', function () {
    expect(GitCredentialHelper::deriveHostFromApiUrl('https://api.github.com'))
        ->toBe('https://github.com');
});

test('deriveHostFromApiUrl handles GH Enterprise', function () {
    expect(GitCredentialHelper::deriveHostFromApiUrl('https://github.example.com/api/v3'))
        ->toBe('https://github.example.com');
});

test('deriveHostFromApiUrl falls back to https://github.com on bad input', function () {
    expect(GitCredentialHelper::deriveHostFromApiUrl(''))
        ->toBe('https://github.com');
});

test('deriveHostFromApiUrl handles parse_url returning false', function () {
    // parse_url returns false on certain malformed inputs; constructor must
    // not error or warn on array access.
    expect(GitCredentialHelper::deriveHostFromApiUrl('http:///bad'))
        ->toBe('https://github.com');
});

test('deriveHostFromApiUrl preserves non-standard port', function () {
    expect(GitCredentialHelper::deriveHostFromApiUrl('https://ghe.example.com:8443/api/v3'))
        ->toBe('https://ghe.example.com:8443');
});

test('configKey includes port for ported GHE base url', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_test',
        apiBaseUrl: 'https://api.ghe.example.com:8443',
    );

    expect($helper->configKey())
        ->toBe('credential.https://ghe.example.com:8443.helper');
});

test('gitConfigEnvForBootstrap returns empty when disabled', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test', enabled: false);

    expect($helper->gitConfigEnvForBootstrap())->toBe([]);
});

test('gitConfigEnvForBootstrap returns empty when no token', function () {
    $helper = new GitCredentialHelper(token: null);

    expect($helper->gitConfigEnvForBootstrap())->toBe([]);
});

test('gitConfigEnvForBootstrap returns GIT_CONFIG_* vars when enabled', function () {
    $helper = new GitCredentialHelper(token: 'ghp_test');

    $env = $helper->gitConfigEnvForBootstrap();

    expect($env)
        ->toHaveKey('GIT_CONFIG_COUNT', '1')
        ->toHaveKey('GIT_CONFIG_KEY_0', 'credential.https://github.com.helper')
        ->and($env['GIT_CONFIG_VALUE_0'])->toContain('ghp_test');
});

test('gitConfigEnvForBootstrap value uses placeholder in env mode', function () {
    $helper = new GitCredentialHelper(
        token: 'ghp_secret',
        mode: GitCredentialHelper::MODE_ENV,
    );

    $env = $helper->gitConfigEnvForBootstrap();

    expect($env['GIT_CONFIG_VALUE_0'])
        ->toContain('${GRAFT_GITHUB_TOKEN}')
        ->not->toContain('ghp_secret');
});

test('env mode shell expansion produces the token at lookup time', function () {
    // The two halves of env mode (helper string + Process env) are tested
    // independently elsewhere. This is the round-trip: invoke the helper
    // via sh -c with GRAFT_GITHUB_TOKEN set in the env, and confirm the
    // helper's stdout contains the literal token.
    $helper = new GitCredentialHelper(
        token: 'ghp_secret_e2e',
        mode: GitCredentialHelper::MODE_ENV,
    );

    $command = substr($helper->configValue(), 1);
    $env = ['GRAFT_GITHUB_TOKEN' => 'ghp_secret_e2e'];

    $process = new Process(['sh', '-c', $command], null, $env);
    $process->run();

    expect($process->getOutput())
        ->toContain('username=x-access-token')
        ->toContain('password=ghp_secret_e2e');
});

test('constructor rejects newline in username', function () {
    new GitCredentialHelper(token: 'ghp_x', username: "user\nhost=evil");
})->throws(InvalidArgumentException::class, 'username');

test('constructor rejects carriage return in username', function () {
    new GitCredentialHelper(token: 'ghp_x', username: "user\rmore");
})->throws(InvalidArgumentException::class);

test('constructor rejects null byte in username', function () {
    new GitCredentialHelper(token: 'ghp_x', username: "user\0bad");
})->throws(InvalidArgumentException::class);

test('constructor rejects newline in token', function () {
    new GitCredentialHelper(token: "ghp_token\nfake=evil");
})->throws(InvalidArgumentException::class, 'token');

test('configValue survives a real shell round-trip', function () {
    $helper = new GitCredentialHelper(token: 'tok"with$danger`back\\slash');

    // configValue starts with `!` (the git helper marker); strip that and the
    // remainder is a plain shell snippet that defines `f` and invokes it.
    $command = substr($helper->configValue(), 1);
    $output = shell_exec('sh -c '.escapeshellarg($command));

    expect($output)->toBe("username=x-access-token\npassword=tok\"with\$danger`back\\slash\n");
});
