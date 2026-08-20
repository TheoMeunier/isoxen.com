<?php

declare(strict_types=1);

namespace Isoxen\Client\Support;

use Closure;

/**
 * Marks the code paths that ship telemetry, so instrumentation can stay out
 * of them.
 *
 * Anything that happens *while sending telemetry* must not itself produce
 * telemetry. The failure case is what makes this essential rather than
 * tidy: when the endpoint is unreachable, the export throws, the exception
 * is reported, the report becomes a span, the span is exported, that export
 * throws... Each failure produces more than one successor (an exception
 * span and a log record), so it doesn't merely fail to converge — it grows
 * exponentially, and it grows fastest exactly when the pipeline is broken
 * and nobody is watching it.
 *
 * Depth-counted rather than a boolean so nested suppression (a transport
 * dispatching from inside an already-suppressed export) restores correctly.
 */
final class Suppression
{
    private static int $depth = 0;

    private static bool $sealed = false;

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Stop this process from shipping anything for the rest of the request.
     *
     * Set when the application is serving telemetry *ingestion* — when it is
     * the collector as well as the sender. Without it the two roles feed
     * each other: an export arrives over HTTP, the process handling it
     * flushes its own telemetry at script shutdown, and because metrics are
     * cumulative there is always something to flush, so that export produces
     * another export. One in, one out, forever, as fast as the queue worker
     * can turn — measured at 66 identical batches per second.
     *
     * Scoped suppression can't cover this: the flush happens at shutdown,
     * long after any `run()` block has exited. Hence a flag that stays set.
     */
    public static function seal(): void
    {
        self::$sealed = true;
    }

    /**
     * Lift the seal at the start of a request that *is* worth recording.
     *
     * Only meaningful under a long-running server (Octane), where one
     * process serves many requests and must not stay sealed because an
     * earlier one happened to be an ingestion call.
     */
    public static function unseal(): void
    {
        self::$sealed = false;
    }

    public static function sealed(): bool
    {
        return self::$sealed;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }
}
