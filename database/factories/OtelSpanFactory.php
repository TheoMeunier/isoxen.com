<?php

namespace Database\Factories;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<OtelSpan>
 */
class OtelSpanFactory extends Factory
{
    /**
     * @var class-string<OtelSpan>
     */
    protected $model = OtelSpan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start         = Date::now()->subMinutes(fake()->numberBetween(0, 60));
        $durationNanos = fake()->numberBetween(1_000_000, 500_000_000);

        return [
            'project_id'          => Project::factory(),
            'trace_id'            => Str::lower(fake()->regexify('[0-9a-f]{32}')),
            'span_id'             => Str::lower(fake()->regexify('[0-9a-f]{16}')),
            'parent_span_id'      => null,
            'name'                => 'GET /'.fake()->slug(),
            'type'                => 'request',
            'kind'                => 2,
            'time'                => $start,
            'end_time'            => $start->addMicroseconds(intdiv($durationNanos, 1_000)),
            'duration_nanos'      => $durationNanos,
            'status_code'         => 0,
            'status_message'      => null,
            'resource_attributes' => [],
            'attributes'          => [],
            'raw'                 => [],
            'created_at'          => Date::now(),
        ];
    }

    public function errored(): self
    {
        return $this->state(fn (): array => ['status_code' => OtelSpan::STATUS_ERROR]);
    }

    public function ofType(string $type): self
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
