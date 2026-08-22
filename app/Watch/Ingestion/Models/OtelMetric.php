<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Models;

use Database\Factories\OtelMetricFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property string|null $name
 * @property string|null $unit
 * @property string|null $type
 * @property DateTimeImmutable $time
 * @property float|null $value
 * @property array<string, mixed>|null $resource_attributes
 * @property array<string, mixed>|null $attributes
 * @property array<string, mixed>|null $raw
 */
#[Table(name: 'otel_metrics')]
class OtelMetric extends OtelRecord
{
    /** @use HasFactory<OtelMetricFactory> */
    use HasFactory;

    /**
     * Declared explicitly since Laravel's factory guessing assumes App\Models\*.
     */
    protected static function newFactory(): OtelMetricFactory
    {
        return OtelMetricFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time'                => 'immutable_datetime',
            'created_at'          => 'immutable_datetime',
            'value'               => 'float',
            'resource_attributes' => 'array',
            'attributes'          => 'array',
            'raw'                 => 'array',
        ];
    }
}
