<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

use DateTimeImmutable;

final readonly class LogRow
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
        public ?int $severityNumber,
        public ?string $severityText,
        public ?string $body,
        public DateTimeImmutable $time,
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
            'severity_number'     => $this->severityNumber,
            'severity_text'       => $this->severityText,
            'body'                => $this->body,
            'time'                => DatabaseTimestamp::format($this->time),
            'resource_attributes' => json_encode($this->resourceAttributes),
            'attributes'          => json_encode($this->attributes),
            'raw'                 => json_encode($this->raw),
            'created_at'          => $this->createdAt,
        ];
    }
}
