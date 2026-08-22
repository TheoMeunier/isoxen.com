<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

use DateTimeImmutable;
use Illuminate\Support\Carbon;

final class OtlpTimestamp
{
    public static function toDateTime(string|int|null $unixNano): ?DateTimeImmutable
    {
        $carbon = self::toCarbon($unixNano);

        return !$carbon instanceof \Illuminate\Support\Carbon ? null : DateTimeImmutable::createFromInterface($carbon);
    }

    private static function toCarbon(string|int|null $unixNano): ?Carbon
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

        return \Illuminate\Support\Facades\Date::createFromTimestamp($seconds)->addMicroseconds($microseconds);
    }
}
