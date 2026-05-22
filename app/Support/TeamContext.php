<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves the "current team" for the request lifecycle. By default it follows
 * the authenticated user's team, but it can be overridden (e.g. queued jobs,
 * console commands or tests) and temporarily suspended for cross-tenant work.
 */
class TeamContext
{
    protected static ?int $teamId = null;

    protected static bool $resolved = false;

    protected static bool $suppressed = false;

    public static function id(): ?int
    {
        if (static::$suppressed) {
            return null;
        }

        if (! static::$resolved) {
            static::$teamId = auth()->user()?->team_id;
            static::$resolved = true;
        }

        return static::$teamId;
    }

    public static function set(?int $teamId): void
    {
        static::$teamId = $teamId;
        static::$resolved = true;
    }

    /**
     * Run a callback without team scoping (e.g. global library lookups).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutScope(callable $callback): mixed
    {
        $previous = static::$suppressed;
        static::$suppressed = true;

        try {
            return $callback();
        } finally {
            static::$suppressed = $previous;
        }
    }

    public static function flush(): void
    {
        static::$teamId = null;
        static::$resolved = false;
        static::$suppressed = false;
    }
}
