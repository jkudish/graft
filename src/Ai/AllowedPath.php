<?php

declare(strict_types=1);

namespace Graft\Ai;

use InvalidArgumentException;

class AllowedPath
{
    /**
     * Resolve a tool repo_path against the configured allowlist.
     *
     * An empty path defaults to base_path() when allow_base_path is enabled.
     *
     * @throws InvalidArgumentException when the path is missing or not allowlisted
     */
    public static function resolve(string $repoPath = ''): string
    {
        $requested = trim($repoPath);

        if ($requested === '') {
            if (! self::allowBasePath()) {
                throw new InvalidArgumentException('Repository path is not allowlisted.');
            }

            $requested = base_path();
        }

        $resolved = self::canonicalize($requested);
        $roots = self::allowedRoots();

        if ($roots === [] || ! self::isInsideAllowedRoot($resolved, $roots)) {
            throw new InvalidArgumentException('Repository path is not allowlisted.');
        }

        return $resolved;
    }

    /**
     * Normalize a filesystem path, preferring realpath for existing prefixes.
     */
    public static function canonicalize(string $path): string
    {
        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }

        $absolute = self::absolutePath($path);
        $separator = DIRECTORY_SEPARATOR;
        $segments = [];

        foreach (explode($separator, str_replace(['\\', '/'], $separator, $absolute)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $remaining = $segments;
        $resolvedPrefix = '';

        while ($remaining !== []) {
            $candidate = $separator.implode($separator, $remaining);
            $realPrefix = realpath($candidate);

            if ($realPrefix !== false) {
                $resolvedPrefix = $realPrefix;
                break;
            }

            array_pop($remaining);
        }

        $suffix = array_slice($segments, count($remaining));

        if ($resolvedPrefix === '') {
            return $separator.implode($separator, $segments);
        }

        return $suffix === []
            ? $resolvedPrefix
            : $resolvedPrefix.$separator.implode($separator, $suffix);
    }

    /**
     * @return list<string>
     */
    public static function allowedRoots(): array
    {
        $roots = [];

        foreach (self::configuredRepos() as $root) {
            $roots[] = self::canonicalize($root);
        }

        if (self::allowBasePath()) {
            $base = self::canonicalize(base_path());
            if (! in_array($base, $roots, true)) {
                $roots[] = $base;
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * @param  list<string>  $roots
     */
    private static function isInsideAllowedRoot(string $resolved, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($resolved === $root || str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function configuredRepos(): array
    {
        $configured = config('graft.ai.allowed_repos', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return [];
        }

        $roots = [];

        foreach ($configured as $root) {
            $trimmed = trim((string) $root);
            if ($trimmed !== '') {
                $roots[] = $trimmed;
            }
        }

        return array_values($roots);
    }

    private static function allowBasePath(): bool
    {
        return (bool) config('graft.ai.allow_base_path', true);
    }

    private static function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        $cwd = getcwd();

        return ($cwd !== false ? $cwd : base_path()).DIRECTORY_SEPARATOR.$path;
    }
}
