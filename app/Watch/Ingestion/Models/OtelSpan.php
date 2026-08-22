<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Models;

use Database\Factories\OtelSpanFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property string|null $trace_id
 * @property string|null $span_id
 * @property string|null $parent_span_id
 * @property string|null $name
 * @property string|null $type
 * @property int|null $kind
 * @property DateTimeImmutable $time
 * @property DateTimeImmutable|null $end_time
 * @property int|null $duration_nanos
 * @property int|null $status_code
 * @property string|null $status_message
 * @property array<string, mixed>|null $resource_attributes
 * @property array<string, mixed>|null $attributes
 * @property array<string, mixed>|null $raw
 */
#[Table(name: 'otel_spans')]
class OtelSpan extends OtelRecord
{
    /** @use HasFactory<OtelSpanFactory> */
    use HasFactory;

    /**
     * Declared explicitly since Laravel's factory guessing assumes App\Models\*.
     */
    protected static function newFactory(): OtelSpanFactory
    {
        return OtelSpanFactory::new();
    }

    /**
     * OTEL status code 2 is ERROR; Unset and Ok both count as healthy.
     */
    public const STATUS_ERROR = 2;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time'                => 'immutable_datetime',
            'end_time'            => 'immutable_datetime',
            'created_at'          => 'immutable_datetime',
            'kind'                => 'integer',
            'status_code'         => 'integer',
            'duration_nanos'      => 'integer',
            'resource_attributes' => 'array',
            'attributes'          => 'array',
            'raw'                 => 'array',
        ];
    }
}
