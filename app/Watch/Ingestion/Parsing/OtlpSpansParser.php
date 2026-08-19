<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use Illuminate\Support\Carbon;

/**
 * Flattens an OTLP/JSON `ExportTraceServiceRequest` payload into rows ready
 * to be inserted into the `otel_spans` table.
 */
final class OtlpSpansParser
{
    /**
     * @param  array<string, mixed>  $payload
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
                        // A span without a start time can't be placed on the
                        // hypertable's time axis, so it's dropped rather than
                        // stored with a made-up timestamp.
                        continue;
                    }

                    $attributes = OtlpAttributes::toArray($span['attributes'] ?? []);

                    $rows[] = [
                        'project_id' => $projectId,
                        'trace_id' => $span['traceId'] ?? null,
                        'span_id' => $span['spanId'] ?? null,
                        'parent_span_id' => $span['parentSpanId'] ?? null,
                        'name' => $span['name'] ?? null,
                        // Not a standard OTLP field: populated from the
                        // `isoxen.type` attribute set by isoxen's own
                        // instrumentation client, used to drive the
                        // project's sidebar (Requests/Jobs/Queries/...).
                        // Spans from a generic OTEL SDK won't set this and
                        // are stored as uncategorized (null).
                        'type' => $attributes['isoxen.type'] ?? null,
                        'kind' => $span['kind'] ?? null,
                        'time' => $startTime,
                        'end_time' => OtlpTimestamp::toCarbon($span['endTimeUnixNano'] ?? null),
                        'duration_nanos' => self::durationNanos($span),
                        'status_code' => $span['status']['code'] ?? null,
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
     * @param  array<string, mixed>  $span
     */
    private static function durationNanos(array $span): ?int
    {
        if (! isset($span['startTimeUnixNano'], $span['endTimeUnixNano'])) {
            return null;
        }

        return (int) $span['endTimeUnixNano'] - (int) $span['startTimeUnixNano'];
    }
}
