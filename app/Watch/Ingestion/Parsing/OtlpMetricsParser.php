<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use Illuminate\Support\Carbon;

final class OtlpMetricsParser
{
    private const TYPES = ['gauge', 'sum', 'histogram', 'exponentialHistogram', 'summary'];

    /**
     * @param array<string, mixed> $payload
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
                            'time' => OtlpTimestamp::toDatabaseString($dataPoint['timeUnixNano'] ?? null),
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
     * @param array<string, mixed> $metric
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
     * @param array<string, mixed> $dataPoint
     */
    private static function numericValue(array $dataPoint): ?float
    {
        return match (true) {
            array_key_exists('asDouble', $dataPoint) => (float)$dataPoint['asDouble'],
            array_key_exists('asInt', $dataPoint) => (float)$dataPoint['asInt'],
            array_key_exists('sum', $dataPoint) => (float)$dataPoint['sum'],
            default => null,
        };
    }
}
