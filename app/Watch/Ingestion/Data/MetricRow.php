<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

use DateTimeImmutable;

final readonly class MetricRow
{
    /**
     * @param  array<string, mixed>  $resourceAttributes
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $projectId,
        public ?string $name,
        public ?string $unit,
        public string $type,
        public DateTimeImmutable $time,
        public ?float $value,
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
            'name'                => $this->name,
            'unit'                => $this->unit,
            'type'                => $this->type,
            'time'                => DatabaseTimestamp::format($this->time),
            'value'               => $this->value,
            'resource_attributes' => json_encode($this->resourceAttributes),
            'attributes'          => json_encode($this->attributes),
            'raw'                 => json_encode($this->raw),
            'created_at'          => $this->createdAt,
        ];
    }
}
