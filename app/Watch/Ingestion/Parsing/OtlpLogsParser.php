<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Data\LogRow;
use App\Watch\Ingestion\Data\Otlp\LogRecord;
use App\Watch\Ingestion\Data\Otlp\LogsPayload;
use App\Watch\Ingestion\Data\Otlp\ResourceLogs;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final class OtlpLogsParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, LogRow>
     */
    public static function toRows(int $projectId, array $payload): Collection
    {
        $now = DateTimeImmutable::createFromInterface(\Illuminate\Support\Facades\Date::now());

        return LogsPayload::fromArray($payload)
            ->resourceLogs
            ->flatMap(fn (ResourceLogs $resource): Collection => $resource->logRecords
                ->map(fn (LogRecord $record): ?LogRow => self::toRow($projectId, $resource, $record, $now)))
            ->filter()
            ->values();
    }

    private static function toRow(
        int $projectId,
        ResourceLogs $resource,
        LogRecord $record,
        DateTimeImmutable $now,
    ): ?LogRow {
        if (!$record->time instanceof \DateTimeImmutable) {
            return null;
        }

        return new LogRow(
            projectId: $projectId,
            traceId: $record->traceId,
            spanId: $record->spanId,
            severityNumber: $record->severityNumber,
            severityText: $record->severityText,
            body: $record->body,
            time: $record->time,
            resourceAttributes: $resource->resourceAttributes,
            attributes: $record->attributes,
            raw: $record->raw,
            createdAt: $now,
        );
    }
}
