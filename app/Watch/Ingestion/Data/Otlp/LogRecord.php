<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use App\Watch\Ingestion\Support\OtlpValue;
use DateTimeImmutable;

final readonly class LogRecord
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $traceId,
        public ?string $spanId,
        public ?int $severityNumber,
        public ?string $severityText,
        public ?string $body,
        public ?DateTimeImmutable $time,
        public array $attributes,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $logRecord
     */
    public static function fromArray(array $logRecord): self
    {
        return new self(
            traceId: OtlpValue::id($logRecord['traceId'] ?? null, 16),
            spanId: OtlpValue::id($logRecord['spanId'] ?? null, 8),
            severityNumber: OtlpValue::severityNumber($logRecord['severityNumber'] ?? null),
            severityText: OtlpValue::text($logRecord['severityText'] ?? null),
            body: self::body($logRecord['body'] ?? null),
            time: OtlpTimestamp::toDateTime(
                $logRecord['timeUnixNano'] ?? $logRecord['observedTimeUnixNano'] ?? null,
            ),
            attributes: OtlpAttributes::toArray($logRecord['attributes'] ?? []),
            raw: $logRecord,
        );
    }

    private static function body(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        return match (true) {
            array_key_exists('stringValue', $body) => (string) $body['stringValue'],
            default                                => json_encode($body),
        };
    }
}
