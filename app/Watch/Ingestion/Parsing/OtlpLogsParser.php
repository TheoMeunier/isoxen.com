<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use App\Watch\Ingestion\Support\OtlpValue;
use Illuminate\Support\Carbon;

/**
 * Flattens an OTLP/JSON `ExportLogsServiceRequest` payload into rows ready
 * to be inserted into the `otel_logs` table.
 */
final class OtlpLogsParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public static function toRows(int $projectId, array $payload): array
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($payload['resourceLogs'] ?? [] as $resourceLog) {
            $resourceAttributes = OtlpAttributes::toArray($resourceLog['resource']['attributes'] ?? []);

            foreach ($resourceLog['scopeLogs'] ?? [] as $scopeLog) {
                foreach ($scopeLog['logRecords'] ?? [] as $logRecord) {
                    $time = OtlpTimestamp::toCarbon(
                        $logRecord['timeUnixNano'] ?? $logRecord['observedTimeUnixNano'] ?? null,
                    );

                    if ($time === null) {
                        continue;
                    }

                    $rows[] = [
                        'project_id' => $projectId,
                        'trace_id' => OtlpValue::id($logRecord['traceId'] ?? null, 16),
                        'span_id' => OtlpValue::id($logRecord['spanId'] ?? null, 8),
                        'severity_number' => OtlpValue::severityNumber($logRecord['severityNumber'] ?? null),
                        'severity_text' => $logRecord['severityText'] ?? null,
                        'body' => self::body($logRecord['body'] ?? null),
                        'time' => $time,
                        'resource_attributes' => json_encode($resourceAttributes),
                        'attributes' => json_encode(OtlpAttributes::toArray($logRecord['attributes'] ?? [])),
                        'raw' => json_encode($logRecord),
                        'created_at' => $now,
                    ];
                }
            }
        }

        return $rows;
    }

    private static function body(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        return match (true) {
            array_key_exists('stringValue', $body) => (string) $body['stringValue'],
            default => json_encode($body),
        };
    }
}
