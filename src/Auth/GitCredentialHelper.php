<?php

declare(strict_types=1);

namespace Graft\Auth;

use InvalidArgumentException;

/**
 * Encapsulates per-repo git credential helper installation for HTTPS auth.
 *
 * Graft writes a host-scoped helper (e.g. `credential.https://github.com.helper`)
 * to the repo's `.git/config`. Note that `git config <key> <value>` REPLACES
 * any existing value at that exact key — so if the user has run
 * `gh auth setup-git` (which writes a per-host helper at the same key),
 * Graft will overwrite it. Set `enabled=false` to opt out entirely.
 *
 * The `mode=env` variant exists because Symfony Process inherits child env
 * from `$_ENV`/`$_SERVER`, neither of which is populated when Laravel's
 * `config:cache` skips `LoadEnvironmentVariables`. Passing the token
 * explicitly via Process's `$env` array bypasses that pitfall.
 */
final class GitCredentialHelper
{
    public const MODE_BAKED = 'baked';

    public const MODE_ENV = 'env';

    public const ENV_VAR = 'GRAFT_GITHUB_TOKEN';

    public function __construct(
        private ?string $token,
        private bool $enabled = true,
        private string $mode = self::MODE_BAKED,
        private string $username = 'x-access-token',
        private ?string $host = null,
        private string $apiBaseUrl = 'https://api.github.com',
    ) {
        $this->assertSafeForCredentialProtocol('username', $this->username);

        if ($this->token !== null) {
            $this->assertSafeForCredentialProtocol('token', $this->token);
        }

        if (! in_array($this->mode, [self::MODE_BAKED, self::MODE_ENV], true)) {
            throw new InvalidArgumentException(
                "GitCredentialHelper mode must be 'baked' or 'env', got '{$this->mode}'."
            );
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->hasToken();
    }

    public function hasToken(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function configKey(): string
    {
        return sprintf('credential.%s.helper', $this->resolvedHost());
    }

    public function configValue(): string
    {
        // Shell snippet credential helper: git invokes the value as a shell
        // command (because of the leading `!`) and reads `username=` /
        // `password=` from stdout. In env mode, the token is interpolated by
        // the subshell at lookup time using the explicit env Graft passes to
        // every Symfony Process invocation.
        $username = $this->shellEscape($this->username);
        $password = $this->mode === self::MODE_ENV
            ? '${'.self::ENV_VAR.'}'
            : $this->shellEscape($this->token ?? '');

        return sprintf(
            '!f() { echo "username=%s"; echo "password=%s"; }; f',
            $username,
            $password,
        );
    }

    /**
     * Env array to pass to Symfony Process. Empty when not in env mode or
     * when there's no token to share.
     *
     * @return array<string, string>
     */
    public function processEnv(): array
    {
        if (! $this->isEnabled() || $this->mode !== self::MODE_ENV) {
            return [];
        }

        return [self::ENV_VAR => (string) $this->token];
    }

    /**
     * Env vars that inject this helper as ephemeral git config for a single
     * `git` invocation, via git's `GIT_CONFIG_COUNT` / `GIT_CONFIG_KEY_N` /
     * `GIT_CONFIG_VALUE_N` mechanism (git 2.31+). This bootstraps auth for
     * `git clone` itself — without it, the persisted `.git/config` helper
     * only takes effect AFTER the clone has already succeeded, which fails
     * for private repos.
     *
     * Passing via env (not `-c <key>=<value>` CLI args) keeps the value out
     * of `ps`-style process listings; `/proc/<pid>/environ` is mode 600 on
     * Linux.
     *
     * Returns `[]` when disabled or token-less so callers can unconditionally
     * spread it into a Process env array.
     *
     * @return array<string, string>
     */
    public function gitConfigEnvForBootstrap(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        return [
            'GIT_CONFIG_COUNT' => '1',
            'GIT_CONFIG_KEY_0' => $this->configKey(),
            'GIT_CONFIG_VALUE_0' => $this->configValue(),
        ];
    }

    public function resolvedHost(): string
    {
        if ($this->host !== null && $this->host !== '') {
            return rtrim($this->host, '/');
        }

        return self::deriveHostFromApiUrl($this->apiBaseUrl);
    }

    /**
     * Derive a credential host (scheme + host) from a platform API base URL.
     *
     * Examples:
     *   https://api.github.com            → https://github.com
     *   https://github.example.com/api/v3 → https://github.example.com
     *
     * The `api.` prefix strip is a heuristic that works for github.com and
     * the canonical GHE pattern; non-canonical setups should override via
     * the `host` config.
     */
    public static function deriveHostFromApiUrl(string $apiUrl): string
    {
        $parsed = parse_url($apiUrl);

        if (! is_array($parsed)) {
            return 'https://github.com';
        }

        $host = $parsed['host'] ?? null;
        $scheme = $parsed['scheme'] ?? 'https';
        $port = $parsed['port'] ?? null;

        if (! is_string($host) || $host === '') {
            return 'https://github.com';
        }

        $host = preg_replace('/^api\./', '', $host) ?? $host;

        // Git's credential URL matching is port-aware, so a GHE install on a
        // non-standard port needs the port in the helper key.
        $portSuffix = is_int($port) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$portSuffix}";
    }

    /**
     * Escape a value for inclusion inside a double-quoted shell string used
     * in a non-interactive `sh -c` invocation. Newline / null guards happen
     * separately in the constructor.
     */
    private function shellEscape(string $value): string
    {
        return addcslashes($value, '"\\$`');
    }

    /**
     * Reject characters that would break the git credential protocol
     * (newline-terminated key=value pairs read from helper stdout). A `\n`
     * in either field would emit a fake protocol line that git's parser
     * trusts. GitHub PATs are alphanumeric+underscore so the token check is
     * defense-in-depth; the username is operator-controlled config and is
     * the realistic vector.
     */
    private function assertSafeForCredentialProtocol(string $field, string $value): void
    {
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException(
                "GitCredentialHelper {$field} must not contain CR, LF, or NUL characters."
            );
        }
    }
}
