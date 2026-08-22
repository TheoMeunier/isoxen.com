<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

use DateTimeImmutable;

final class DatabaseTimestamp
{
    public static function format(?DateTimeImmutable $time): ?string
    {
        return $time?->format('Y-m-d H:i:s.u');
    }
}
