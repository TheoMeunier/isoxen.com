<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Parsing;

use App\Watch\Ingestion\Data\Otlp\ResourceSpans;
use App\Watch\Ingestion\Data\Otlp\Span;
use App\Watch\Ingestion\Data\Otlp\TracePayload;
use App\Watch\Ingestion\Data\SpanRow;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final class OtlpSpansParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, SpanRow>
     */
    public static function toRows(int $projectId, array $payload): Collection
    {
        $now = DateTimeImmutable::createFromInterface(\Illuminate\Support\Facades\Date::now());

        return TracePayload::fromArray($payload)
            ->resourceSpans
            ->flatMap(fn (ResourceSpans $resource): Collection => $resource->spans
                ->map(fn (Span $span): ?SpanRow => self::toRow($projectId, $resource, $span, $now)))
            ->filter()
            ->values();
    }

    private static function toRow(int $projectId, ResourceSpans $resource, Span $span, DateTimeImmutable $now): ?SpanRow
    {
        if (!$span->startTime instanceof \DateTimeImmutable) {
            return null;
        }

        return new SpanRow(
            projectId: $projectId,
            traceId: $span->traceId,
            spanId: $span->spanId,
            parentSpanId: $span->parentSpanId,
            name: $span->name,
            type: $span->type,
            kind: $span->kind,
            time: $span->startTime,
            endTime: $span->endTime,
            durationNanos: $span->durationNanos,
            statusCode: $span->statusCode,
            statusMessage: $span->statusMessage,
            resourceAttributes: $resource->resourceAttributes,
            attributes: $span->attributes,
            raw: $span->raw,
            createdAt: $now,
        );
    }
}
