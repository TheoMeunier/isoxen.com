<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

use DateTimeImmutable;

final class OtlpTimestamp
{
    /** Nanoseconds since the epoch, truncated to the microseconds Postgres stores (ADR-0001). */
    public static function toDateTime(string|int|null $unixNano): ?DateTimeImmutable
    {
        if ($unixNano === null) {
            return null;
        }

        $nanos = (int) $unixNano;

        if ($nanos <= 0) {
            return null;
        }

        $seconds = intdiv($nanos, 1_000_000_000);
        $microseconds = str_pad((string) intdiv($nanos % 1_000_000_000, 1_000), 6, '0', STR_PAD_LEFT);

        // The 'U' format anchors the result to UTC, as Carbon::createFromTimestamp() did here before.
        return DateTimeImmutable::createFromFormat('U u', $seconds.' '.$microseconds) ?: null;
    }
}
