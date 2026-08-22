<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use App\Watch\Ingestion\Support\OtlpValue;
use Illuminate\Support\Collection;

final readonly class Metric
{
    private const array TYPES = ['gauge', 'sum', 'histogram', 'exponentialHistogram', 'summary'];

    /**
     * @param  Collection<int, DataPoint>  $dataPoints
     */
    public function __construct(
        public ?string $name,
        public ?string $unit,
        public string $type,
        public Collection $dataPoints,
    ) {}

    /**
     * @param  array<string, mixed>  $metric
     * @return self|null null when the metric carries none of the OTEL types this collector knows
     */
    public static function fromArray(array $metric): ?self
    {
        $type = self::detectType($metric);

        if ($type === null) {
            return null;
        }

        return new self(
            name: OtlpValue::text($metric['name'] ?? null),
            unit: OtlpValue::text($metric['unit'] ?? null),
            type: self::normalizeTypeName($type),
            dataPoints: collect($metric[$type]['dataPoints'] ?? [])
                ->map(fn (array $dataPoint): DataPoint => DataPoint::fromArray($dataPoint))
                ->values(),
        );
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
            default                => $type,
        };
    }
}
