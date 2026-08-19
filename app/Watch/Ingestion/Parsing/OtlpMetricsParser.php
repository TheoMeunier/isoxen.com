<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use Illuminate\Support\Carbon;

/**
 * Flattens an OTLP/JSON `ExportMetricsServiceRequest` payload into rows
 * ready to be inserted into the `otel_metrics` table.
 *
 * Every OTEL metric type (gauge, sum, histogram, exponential histogram,
 * summary) is stored as one row per data point. Only a single scalar
 * `value` is extracted for simple types (gauge/sum); richer shapes
 * (histogram buckets, summary quantiles, ...) are preserved in full in the
 * `raw` column so no information is lost even though this MVP doesn't
 * decompose them into columns yet.
 */
final class OtlpMetricsParser
{
    private const TYPES = ['gauge', 'sum', 'histogram', 'exponentialHistogram', 'summary'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public static function toRows(int $projectId, array $payload): array
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($payload['resourceMetrics'] ?? [] as $resourceMetric) {
            $resourceAttributes = OtlpAttributes::toArray($resourceMetric['resource']['attributes'] ?? []);

            foreach ($resourceMetric['scopeMetrics'] ?? [] as $scopeMetric) {
                foreach ($scopeMetric['metrics'] ?? [] as $metric) {
                    $type = self::detectType($metric);

                    if ($type === null) {
                        continue;
                    }

                    foreach ($metric[$type]['dataPoints'] ?? [] as $dataPoint) {
                        $time = OtlpTimestamp::toCarbon($dataPoint['timeUnixNano'] ?? null);

                        if ($time === null) {
                            continue;
                        }

                        $rows[] = [
                            'project_id' => $projectId,
                            'name' => $metric['name'] ?? null,
                            'unit' => $metric['unit'] ?? null,
                            'type' => self::normalizeTypeName($type),
                            'time' => $time,
                            'value' => self::numericValue($dataPoint),
                            'resource_attributes' => json_encode($resourceAttributes),
                            'attributes' => json_encode(OtlpAttributes::toArray($dataPoint['attributes'] ?? [])),
                            'raw' => json_encode($dataPoint),
                            'created_at' => $now,
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $metric
     */
    private static function detectType(array $metric): ?string
    {
        foreach (self::TYPES as $type) {
            if (isset($metric[$type])) {
                return $type;
            }
        }

        return null;
    }

    private static function normalizeTypeName(string $type): string
    {
        return match ($type) {
            'exponentialHistogram' => 'exponential_histogram',
            default => $type,
        };
    }

    /**
     * @param  array<string, mixed>  $dataPoint
     */
    private static function numericValue(array $dataPoint): ?float
    {
        return match (true) {
            array_key_exists('asDouble', $dataPoint) => (float) $dataPoint['asDouble'],
            array_key_exists('asInt', $dataPoint) => (float) $dataPoint['asInt'],
            // Histograms/summaries don't have a single scalar value; the
            // aggregate sum is stored as a rough approximation, the full
            // shape (buckets/quantiles) stays available in `raw`.
            array_key_exists('sum', $dataPoint) => (float) $dataPoint['sum'],
            default => null,
        };
    }
}
