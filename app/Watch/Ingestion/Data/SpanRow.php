<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

use DateTimeImmutable;

final readonly class SpanRow
{
    /**
     * @param  array<string, mixed>  $resourceAttributes
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $projectId,
        public ?string $traceId,
        public ?string $spanId,
        public ?string $parentSpanId,
        public ?string $name,
        public ?string $type,
        public ?int $kind,
        public DateTimeImmutable $time,
        public ?DateTimeImmutable $endTime,
        public ?int $durationNanos,
        public ?int $statusCode,
        public ?string $statusMessage,
        public array $resourceAttributes,
        public array $attributes,
        public array $raw,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabaseRow(): array
    {
        return [
            'project_id'          => $this->projectId,
            'trace_id'            => $this->traceId,
            'span_id'             => $this->spanId,
            'parent_span_id'      => $this->parentSpanId,
            'name'                => $this->name,
            'type'                => $this->type,
            'kind'                => $this->kind,
            'time'                => DatabaseTimestamp::format($this->time),
            'end_time'            => DatabaseTimestamp::format($this->endTime),
            'duration_nanos'      => $this->durationNanos,
            'status_code'         => $this->statusCode,
            'status_message'      => $this->statusMessage,
            'resource_attributes' => json_encode($this->resourceAttributes),
            'attributes'          => json_encode($this->attributes),
            'raw'                 => json_encode($this->raw),
            'created_at'          => $this->createdAt,
        ];
    }
}
