<?php

use App\Watch\Ingestion\Jobs\StoreOtlpMetrics;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function otlpMetricsPayload(): array
{
    return [
        'resourceMetrics' => [
            [
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'checkout-api']],
                    ],
                ],
                'scopeMetrics' => [
                    [
                        'metrics' => [
                            [
                                'name' => 'http.server.duration',
                                'unit' => 'ms',
                                'gauge' => [
                                    'dataPoints' => [
                                        [
                                            'timeUnixNano' => '1660296023390000000',
                                            'asDouble' => 42.5,
                                            'attributes' => [
                                                ['key' => 'http.route', 'value' => ['stringValue' => '/orders']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('a valid metrics payload is accepted and queued for storage', function () {
    Queue::fake();

    $project = Project::factory()->create();

    $this->postJson(route('otel.metrics'), otlpMetricsPayload(), [
        'Authorization' => "Bearer {$project->token}",
    ])->assertOk();

    Queue::assertPushed(StoreOtlpMetrics::class, fn (StoreOtlpMetrics $job): bool => $job->projectId === $project->id);
});

test('a metrics payload missing "resourceMetrics" is rejected', function () {
    $project = Project::factory()->create();

    $this->postJson(route('otel.metrics'), ['nope' => true], [
        'Authorization' => "Bearer {$project->token}",
    ])->assertStatus(400);
});

test('the store metrics job inserts one row per data point', function () {
    $project = Project::factory()->create();

    (new StoreOtlpMetrics($project->id, otlpMetricsPayload()))->handle();

    $this->assertDatabaseHas('otel_metrics', [
        'project_id' => $project->id,
        'name' => 'http.server.duration',
        'type' => 'gauge',
        'value' => 42.5,
    ]);
});
