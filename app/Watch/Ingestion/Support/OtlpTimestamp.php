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

        $seconds      = intdiv($nanos, 1_000_000_000);
        $microseconds = intdiv($nanos % 1_000_000_000, 1_000);

        return Carbon::createFromTimestamp($seconds)->addMicroseconds($microseconds);
    }

    /**
     * Same conversion as {@see self::toCarbon()}, formatted for a raw
     * `DB::table()->insert()` row.
     *
     * `DB::table()` doesn't run bound values through any connection-specific
     * date formatting -- a Carbon instance is stringified via
     * `Illuminate\Database\Grammar::getDateFormat()`, which defaults to
     * `'Y-m-d H:i:s'` (no sub-second precision) for every driver except
     * SqlServer. Passing a raw Carbon here silently truncates spans/logs to
     * whole-second timestamps, which breaks duration math for anything
     * faster than a second. Formatting explicitly to microsecond precision
     * keeps the timestamp intact regardless of the connection's grammar.
     */
    public static function toDatabaseString(string|int|null $unixNano): ?string
    {
        return self::toCarbon($unixNano)?->format('Y-m-d H:i:s.u');
    }
}
