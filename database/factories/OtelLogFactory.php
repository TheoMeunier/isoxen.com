<?php

namespace Database\Factories;

use App\Watch\Ingestion\Models\OtelLog;
use App\Watch\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<OtelLog>
 */
class OtelLogFactory extends Factory
{
    /**
     * @var class-string<OtelLog>
     */
    protected $model = OtelLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id'          => Project::factory(),
            'trace_id'            => Str::lower(fake()->regexify('[0-9a-f]{32}')),
            'span_id'             => Str::lower(fake()->regexify('[0-9a-f]{16}')),
            'severity_number'     => 9,
            'severity_text'       => 'INFO',
            'body'                => fake()->sentence(),
            'time'                => Date::now()->subMinutes(fake()->numberBetween(0, 60)),
            'resource_attributes' => [],
            'attributes'          => [],
            'raw'                 => [],
            'created_at'          => Date::now(),
        ];
    }

    public function withSeverity(int $severityNumber, string $severityText): self
    {
        return $this->state(fn (): array => [
            'severity_number' => $severityNumber,
            'severity_text'   => $severityText,
        ]);
    }
}
