<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Data\MetricRow;
use App\Watch\Ingestion\Data\Otlp\DataPoint;
use App\Watch\Ingestion\Data\Otlp\Metric;
use App\Watch\Ingestion\Data\Otlp\MetricsPayload;
use App\Watch\Ingestion\Data\Otlp\ResourceMetrics;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OtlpMetricsParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, MetricRow>
     */
    public static function toRows(int $projectId, array $payload): Collection
    {
        $now = DateTimeImmutable::createFromInterface(\Illuminate\Support\Facades\Date::now());

        return MetricsPayload::fromArray($payload)
            ->resourceMetrics
            ->flatMap(fn (ResourceMetrics $resource): Collection => $resource->metrics
                ->flatMap(fn (Metric $metric): Collection => $metric->dataPoints->map(
                    fn (DataPoint $point): ?MetricRow => self::toRow($projectId, $resource, $metric, $point, $now),
                )))
            ->filter()
            ->values();
    }

    private static function toRow(
        int $projectId,
        ResourceMetrics $resource,
        Metric $metric,
        DataPoint $point,
        DateTimeImmutable $now,
    ): ?MetricRow {
        if (!$point->time instanceof \DateTimeImmutable) {
            return null;
        }

        return new MetricRow(
            projectId: $projectId,
            name: $metric->name,
            unit: $metric->unit,
            type: $metric->type,
            time: $point->time,
            value: $point->value,
            resourceAttributes: $resource->resourceAttributes,
            attributes: $point->attributes,
            raw: $point->raw,
            createdAt: $now,
        );
    }
}
