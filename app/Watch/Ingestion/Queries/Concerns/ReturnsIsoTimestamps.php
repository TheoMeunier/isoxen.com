<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries\Concerns;


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

            $row->{$column} = \Illuminate\Support\Facades\Date::parse($row->{$column})->format('Y-m-d\TH:i:s.uP');
        }

        return $row;
    }
}
