<?php

declare(strict_types=1);

namespace Isoxen\Client\Support;

/**
 * Lightweight per-process counters for activity that isn't worth its own
 * span but is still worth surfacing on the request it happened during —
 * currently just how many Eloquent models were hydrated, and how many
 * filesystem reads/writes occurred.
 *
 * Static and depth-free, unlike {@see Suppression}: there is nothing to
 * nest, just a flat set of named counts that {@see
 * \Isoxen\Client\Instrumentation\Support\Http\Server\TraceRequestMiddleware}
 * resets at the start of each request and reads back at the end. Under a
 * long-running server (Octane) this reset is what keeps one request's
 * counts from leaking into the next.
 */
final class RequestActivityCounters
{
    /** @var array<string, int> */
    private static array $counts = [];

    public static function increment(string $key): void
    {
        self::$counts[$key] = (self::$counts[$key] ?? 0) + 1;
    }

    public static function get(string $key): int
    {
        return self::$counts[$key] ?? 0;
    }

    public static function reset(): void
    {
        self::$counts = [];
    }
}
