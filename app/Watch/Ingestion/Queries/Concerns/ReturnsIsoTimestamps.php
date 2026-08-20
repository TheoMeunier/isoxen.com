<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries\Concerns;

use Illuminate\Support\Carbon;

/**
 * Normalizes timestamp columns into ISO-8601 before they reach the browser.
 *
 * These rows come from the query builder, not Eloquent, so nothing casts
 * them: `time` arrives as whatever string the driver produced. Postgres
 * gives `2026-08-19 21:14:02.123456+00`, which is *not* ISO-8601 — a space
 * instead of a `T`, an offset of `+00` instead of `+00:00`. `new Date()` on
 * a string like that is implementation-defined, and browsers that give up
 * on the offset read it as local time, so a viewer in Paris sees every
 * timestamp two hours out.
 *
 * Emitting real ISO-8601 makes the parse well-defined, and the browser can
 * then render it in the reader's own timezone — which is the whole point of
 * having stored an absolute instant.
 */
trait ReturnsIsoTimestamps
{
    /**
     * @param  array<int, string>  $columns
     */
    protected function toIso(object $row, array $columns = ['time']): object
    {
        foreach ($columns as $column) {
            if (! property_exists($row, $column) || $row->{$column} === null) {
                continue;
            }

            $row->{$column} = Carbon::parse($row->{$column})->toIso8601String();
        }

        return $row;
    }
}
