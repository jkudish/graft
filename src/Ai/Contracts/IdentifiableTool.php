<?php

declare(strict_types=1);

namespace Graft\Ai\Contracts;

/**
 * Tools that can identify themselves with a colon-notation ID.
 *
 * Use alongside Laravel\Ai\Contracts\Tool. The toolId() method gives a
 * stable string identifier you can use to discover tools via container
 * tagging, build an ID→class map for routing, or expose tools to MCP
 * clients with a deterministic name.
 *
 * ID convention: category:action using kebab-case actions.
 * Examples: graft:git:log, graft:github:list-prs, vault:search.
 */
interface IdentifiableTool
{
    /**
     * Get the unique tool identifier using colon notation.
     *
     * Format: category:action or category:subcategory:action.
     * Convention: kebab-case for multi-word actions.
     */
    public static function toolId(): string;
}
