<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

use Illuminate\Support\Carbon;

final class OtlpTimestamp
{
    public static function toCarbon(string|int|null $unixNano): ?Carbon
    {
        if ($unixNano === null) {
            return null;
        }

        $nanos = (int)$unixNano;

        if ($nanos <= 0) {
            return null;
        }

        $seconds = intdiv($nanos, 1_000_000_000);
        $microseconds = intdiv($nanos % 1_000_000_000, 1_000);

        return Carbon::createFromTimestamp($seconds)->addMicroseconds($microseconds);
    }

    /** Same as {@see self::toCarbon()}, formatted to microsecond precision for a raw `DB::table()->insert()` row. */
    public static function toDatabaseString(string|int|null $unixNano): ?string
    {
        return self::toCarbon($unixNano)?->format('Y-m-d H:i:s.u');
    }
}
