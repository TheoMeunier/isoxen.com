<?php

use App\Watch\Ingestion\Jobs\StoreOtlpLogs;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function otlpLogsPayload(): array
{
    return [
        'resourceLogs' => [
            [
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'checkout-api']],
                    ],
                ],
                'scopeLogs' => [
                    [
                        'logRecords' => [
                            [
                                'timeUnixNano'   => '1660296023390000000',
                                'severityNumber' => 9,
                                'severityText'   => 'INFO',
                                'body'           => ['stringValue' => 'Order created'],
                                'traceId'        => '5b8aa5a2d2c872e8321cf37308d69df2',
                                'spanId'         => '051581bf3cb55c13',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('a valid logs payload is accepted and queued for storage', function () {
    Queue::fake();

    $project = Project::factory()->create();

    $this->postJson(route('otel.logs'), otlpLogsPayload(), [
        'Authorization' => "Bearer {$project->token}",
    ])->assertOk();

    Queue::assertPushed(StoreOtlpLogs::class, fn (StoreOtlpLogs $job): bool => $job->projectId === $project->id);
});

test('a logs payload missing "resourceLogs" is rejected', function () {
    $project = Project::factory()->create();

    $this->postJson(route('otel.logs'), ['nope' => true], [
        'Authorization' => "Bearer {$project->token}",
    ])->assertStatus(400);
});

test('the store logs job inserts one row per log record', function () {
    $project = Project::factory()->create();

    (new StoreOtlpLogs($project->id, otlpLogsPayload()))->handle();

    $this->assertDatabaseHas('otel_logs', [
        'project_id'    => $project->id,
        'severity_text' => 'INFO',
        'body'          => 'Order created',
        'trace_id'      => '5b8aa5a2d2c872e8321cf37308d69df2',
    ]);
});
