<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use App\Watch\Ingestion\Support\OtlpValue;
use Illuminate\Support\Carbon;

final class OtlpSpansParser
{
    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    public static function toRows(int $projectId, array $payload): array
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($payload['resourceSpans'] ?? [] as $resourceSpan) {
            $resourceAttributes = OtlpAttributes::toArray($resourceSpan['resource']['attributes'] ?? []);

            foreach ($resourceSpan['scopeSpans'] ?? [] as $scopeSpan) {
                foreach ($scopeSpan['spans'] ?? [] as $span) {
                    $startTime = OtlpTimestamp::toCarbon($span['startTimeUnixNano'] ?? null);

                    if ($startTime === null) {
                        continue;
                    }

                    $attributes = OtlpAttributes::toArray($span['attributes'] ?? []);

                    $rows[] = [
                        'project_id' => $projectId,
                        'trace_id' => OtlpValue::id($span['traceId'] ?? null, 16),
                        'span_id' => OtlpValue::id($span['spanId'] ?? null, 8),
                        'parent_span_id' => OtlpValue::id($span['parentSpanId'] ?? null, 8),
                        'name' => $span['name'] ?? null,
                        'type' => $attributes['isoxen.type'] ?? null,
                        'kind' => OtlpValue::spanKind($span['kind'] ?? null),
                        'time' => OtlpTimestamp::toDatabaseString($span['startTimeUnixNano'] ?? null),
                        'end_time' => OtlpTimestamp::toDatabaseString($span['endTimeUnixNano'] ?? null),
                        'duration_nanos' => self::durationNanos($span),
                        'status_code' => OtlpValue::statusCode($span['status']['code'] ?? null),
                        'status_message' => $span['status']['message'] ?? null,
                        'resource_attributes' => json_encode($resourceAttributes),
                        'attributes' => json_encode($attributes),
                        'raw' => json_encode($span),
                        'created_at' => $now,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $span
     */
    private static function durationNanos(array $span): ?int
    {
        if (!isset($span['startTimeUnixNano'], $span['endTimeUnixNano'])) {
            return null;
        }

        return (int)$span['endTimeUnixNano'] - (int)$span['startTimeUnixNano'];
    }
}
