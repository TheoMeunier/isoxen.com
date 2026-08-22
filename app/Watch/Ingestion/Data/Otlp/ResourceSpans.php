<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use App\Watch\Ingestion\Support\OtlpAttributes;
use Illuminate\Support\Collection;

final readonly class ResourceSpans
{
    /**
     * @param  array<string, mixed>  $resourceAttributes
     * @param  Collection<int, Span>  $spans
     */
    public function __construct(
        public array $resourceAttributes,
        public Collection $spans,
    ) {}

    /**
     * @param  array<string, mixed>  $resourceSpan
     */
    public static function fromArray(array $resourceSpan): self
    {
        return new self(
            resourceAttributes: OtlpAttributes::toArray($resourceSpan['resource']['attributes'] ?? []),
            spans: collect($resourceSpan['scopeSpans'] ?? [])
                ->flatMap(fn (array $scopeSpan): array => $scopeSpan['spans'] ?? [])
                ->map(fn (array $span): Span => Span::fromArray($span))
                ->values(),
        );
    }
}
