<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use Illuminate\Support\Collection;

final readonly class MetricsPayload
{
    /**
     * @param  Collection<int, ResourceMetrics>  $resourceMetrics
     */
    public function __construct(
        public Collection $resourceMetrics,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            resourceMetrics: collect($payload['resourceMetrics'] ?? [])
                ->map(fn (array $resourceMetric): ResourceMetrics => ResourceMetrics::fromArray($resourceMetric))
                ->values(),
        );
    }
}
