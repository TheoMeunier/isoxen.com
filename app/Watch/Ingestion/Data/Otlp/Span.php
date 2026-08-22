<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use App\Watch\Ingestion\Support\OtlpValue;
use DateTimeImmutable;

final readonly class Span
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $traceId,
        public ?string $spanId,
        public ?string $parentSpanId,
        public ?string $name,
        public ?string $type,
        public ?int $kind,
        public ?DateTimeImmutable $startTime,
        public ?DateTimeImmutable $endTime,
        public ?int $durationNanos,
        public ?int $statusCode,
        public ?string $statusMessage,
        public array $attributes,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $span
     */
    public static function fromArray(array $span): self
    {
        $attributes = OtlpAttributes::toArray($span['attributes'] ?? []);

        return new self(
            traceId: OtlpValue::id($span['traceId'] ?? null, 16),
            spanId: OtlpValue::id($span['spanId'] ?? null, 8),
            parentSpanId: OtlpValue::id($span['parentSpanId'] ?? null, 8),
            name: OtlpValue::text($span['name'] ?? null),
            type: OtlpValue::text($attributes['isoxen.type'] ?? null),
            kind: OtlpValue::spanKind($span['kind'] ?? null),
            startTime: OtlpTimestamp::toDateTime($span['startTimeUnixNano'] ?? null),
            endTime: OtlpTimestamp::toDateTime($span['endTimeUnixNano'] ?? null),
            durationNanos: self::durationNanos($span),
            statusCode: OtlpValue::statusCode($span['status']['code'] ?? null),
            statusMessage: OtlpValue::text($span['status']['message'] ?? null),
            attributes: $attributes,
            raw: $span,
        );
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
