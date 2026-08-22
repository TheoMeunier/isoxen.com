<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Models;

use Database\Factories\OtelLogFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property string|null $trace_id
 * @property string|null $span_id
 * @property int|null $severity_number
 * @property string|null $severity_text
 * @property string|null $body
 * @property DateTimeImmutable $time
 * @property array<string, mixed>|null $resource_attributes
 * @property array<string, mixed>|null $attributes
 * @property array<string, mixed>|null $raw
 */
#[Table(name: 'otel_logs')]
class OtelLog extends OtelRecord
{
    /** @use HasFactory<OtelLogFactory> */
    use HasFactory;

    /**
     * Declared explicitly since Laravel's factory guessing assumes App\Models\*.
     */
    protected static function newFactory(): OtelLogFactory
    {
        return OtelLogFactory::new();
    }

    /**
     * OTEL severity numbers: ERROR starts at 17, WARN at 13; anything lower reads as info.
     */
    public const SEVERITY_ERROR = 17;

    public const SEVERITY_WARNING = 13;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time'                => 'immutable_datetime',
            'created_at'          => 'immutable_datetime',
            'severity_number'     => 'integer',
            'resource_attributes' => 'array',
            'attributes'          => 'array',
            'raw'                 => 'array',
        ];
    }
}
