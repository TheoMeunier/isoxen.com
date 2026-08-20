<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * The SQL fragment that truncates a `time` column down to its containing
 * hour, as a string key both sides of a query can agree on.
 *
 * Shared by every query that charts something per hour (activity volume,
 * average duration, ...) so the Postgres/SQLite branching -- and the need
 * for `AT TIME ZONE 'UTC'` on Postgres specifically -- only has to be
 * gotten right once. See ActivityTimelineQuery for why the timezone matters.
 */
trait BucketsByHour
{
    protected function bucketExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? 'to_char(date_trunc(\'hour\', "time" AT TIME ZONE \'UTC\'), \'YYYY-MM-DD HH24:00\')'
            : 'strftime(\'%Y-%m-%d %H:00\', "time")';
    }
}
