<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Watch\Ingestion\Models\OtelMetric;
use App\Watch\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<OtelMetric>
 */
class OtelMetricFactory extends Factory
{
    /**
     * @var class-string<OtelMetric>
     */
    protected $model = OtelMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id'          => Project::factory(),
            'name'                => 'app.'.fake()->word(),
            'unit'                => '1',
            'type'                => 'gauge',
            'time'                => Date::now()->subMinutes(fake()->numberBetween(0, 60)),
            'value'               => fake()->randomFloat(2, 0, 100),
            'resource_attributes' => [],
            'attributes'          => [],
            'raw'                 => [],
            'created_at'          => Date::now(),
        ];
    }
}
