<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use App\Watch\Ingestion\Support\OtlpAttributes;
use Illuminate\Support\Collection;

final readonly class ResourceMetrics
{
    /**
     * @param  array<string, mixed>  $resourceAttributes
     * @param  Collection<int, Metric>  $metrics
     */
    public function __construct(
        public array $resourceAttributes,
        public Collection $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $resourceMetric
     */
    public static function fromArray(array $resourceMetric): self
    {
        return new self(
            resourceAttributes: OtlpAttributes::toArray($resourceMetric['resource']['attributes'] ?? []),
            metrics: collect($resourceMetric['scopeMetrics'] ?? [])
                ->flatMap(fn (array $scopeMetric): array => $scopeMetric['metrics'] ?? [])
                ->map(fn (array $metric): ?Metric => Metric::fromArray($metric))
                ->filter()
                ->values(),
        );
    }
}
