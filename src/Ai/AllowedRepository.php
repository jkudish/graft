<?php

declare(strict_types=1);

namespace Graft\Ai;

use InvalidArgumentException;

class AllowedRepository
{
    /**
     * Reject owner/repo values that are outside the configured allowlist.
     *
     * An empty allowlist permits every repository (backward compatible).
     *
     * @throws InvalidArgumentException when the repository is not allowlisted
     */
    public static function assertAllowed(string $repository): void
    {
        if (! self::isAllowed($repository)) {
            throw new InvalidArgumentException("Repository [{$repository}] is not allowlisted.");
        }
    }

    public static function isAllowed(string $repository): bool
    {
        $allowed = self::allowedRepositories();

        if ($allowed === []) {
            return true;
        }

        $normalized = strtolower(trim($repository));

        return $normalized !== '' && in_array($normalized, $allowed, true);
    }

    /**
     * @return list<string>
     */
    public static function allowedRepositories(): array
    {
        $configured = config('graft.ai.allowed_repositories', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return [];
        }

        $repositories = [];

        foreach ($configured as $repository) {
            $normalized = strtolower(trim((string) $repository));
            if ($normalized !== '') {
                $repositories[] = $normalized;
            }
        }

        return array_values(array_unique($repositories));
    }
}
