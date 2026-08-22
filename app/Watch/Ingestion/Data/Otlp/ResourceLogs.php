<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use App\Watch\Ingestion\Support\OtlpAttributes;
use Illuminate\Support\Collection;

final readonly class ResourceLogs
{
    /**
     * @param  array<string, mixed>  $resourceAttributes
     * @param  Collection<int, LogRecord>  $logRecords
     */
    public function __construct(
        public array $resourceAttributes,
        public Collection $logRecords,
    ) {}

    /**
     * @param  array<string, mixed>  $resourceLog
     */
    public static function fromArray(array $resourceLog): self
    {
        return new self(
            resourceAttributes: OtlpAttributes::toArray($resourceLog['resource']['attributes'] ?? []),
            logRecords: collect($resourceLog['scopeLogs'] ?? [])
                ->flatMap(fn (array $scopeLog): array => $scopeLog['logRecords'] ?? [])
                ->map(fn (array $logRecord): LogRecord => LogRecord::fromArray($logRecord))
                ->values(),
        );
    }
}
