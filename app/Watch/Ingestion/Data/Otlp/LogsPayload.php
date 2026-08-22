<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data\Otlp;

use Illuminate\Support\Collection;

/**
 * A decoded OTLP/JSON `ExportLogsServiceRequest` body.
 */
final readonly class LogsPayload
{
    /**
     * @param  Collection<int, ResourceLogs>  $resourceLogs
     */
    public function __construct(
        public Collection $resourceLogs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            resourceLogs: collect($payload['resourceLogs'] ?? [])
                ->map(fn (array $resourceLog): ResourceLogs => ResourceLogs::fromArray($resourceLog))
                ->values(),
        );
    }
}
