<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use Illuminate\Support\Collection;

/**
 * A decoded OTLP/JSON `ExportTraceServiceRequest` body.
 */
final readonly class TracePayload
{
    /**
     * @param  Collection<int, ResourceSpans>  $resourceSpans
     */
    public function __construct(
        public Collection $resourceSpans,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            resourceSpans: collect($payload['resourceSpans'] ?? [])
                ->map(fn (array $resourceSpan): ResourceSpans => ResourceSpans::fromArray($resourceSpan))
                ->values(),
        );
    }
}
