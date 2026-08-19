<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

use Illuminate\Support\Carbon;

/**
 * Converts OTLP's `*UnixNano` fields (nanoseconds since the epoch, encoded
 * as a string in OTLP/JSON to avoid int64 precision loss) into Carbon
 * instances.
 *
 * Note: Postgres `timestamptz` only stores microsecond precision, so the
 * nanosecond component below microsecond resolution is truncated. That's an
 * accepted trade-off for this MVP (see ADR-0001).
 */
final class OtlpTimestamp
{
    public static function toCarbon(string|int|null $unixNano): ?Carbon
    {
        if ($unixNano === null) {
            return null;
        }

        $nanos = (int) $unixNano;

        if ($nanos <= 0) {
            return null;
        }

        $seconds = intdiv($nanos, 1_000_000_000);
        $microseconds = intdiv($nanos % 1_000_000_000, 1_000);

        return Carbon::createFromTimestamp($seconds)->addMicroseconds($microseconds);
    }
}
