<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * The SQL fragment truncating a `time` column to its containing UTC hour, as a key both sides of a query share.
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
