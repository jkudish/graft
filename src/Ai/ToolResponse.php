<?php

declare(strict_types=1);

namespace Graft\Ai;

class ToolResponse
{
    /**
     * Encode a structured tool failure the SDK can distinguish from empty data.
     */
    public static function error(string $message): string
    {
        return self::json([
            'error' => true,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{"error":true,"message":"Failed to encode response."}';
    }
}
