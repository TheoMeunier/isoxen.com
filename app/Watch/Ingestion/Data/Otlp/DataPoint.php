<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Ingestion\Support\OtlpTimestamp;
use DateTimeImmutable;

final readonly class DataPoint
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?DateTimeImmutable $time,
        public ?float $value,
        public array $attributes,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $dataPoint
     */
    public static function fromArray(array $dataPoint): self
    {
        return new self(
            time: OtlpTimestamp::toDateTime($dataPoint['timeUnixNano'] ?? null),
            value: self::numericValue($dataPoint),
            attributes: OtlpAttributes::toArray($dataPoint['attributes'] ?? []),
            raw: $dataPoint,
        );
    }

    /**
     * @param  array<string, mixed>  $dataPoint
     */
    private static function numericValue(array $dataPoint): ?float
    {
        return match (true) {
            array_key_exists('asDouble', $dataPoint) => (float) $dataPoint['asDouble'],
            array_key_exists('asInt', $dataPoint)    => (float) $dataPoint['asInt'],
            array_key_exists('sum', $dataPoint)      => (float) $dataPoint['sum'],
            default                                  => null,
        };
    }
}
