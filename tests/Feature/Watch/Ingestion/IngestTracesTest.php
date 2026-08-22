<?php

use App\Watch\Ingestion\Jobs\StoreOtlpSpans;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function otlpTracesPayload(): array
{
    return [
        'resourceSpans' => [
            [
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'checkout-api']],
                    ],
                ],
                'scopeSpans' => [
                    [
                        'spans' => [
                            [
                                'traceId'           => '5b8aa5a2d2c872e8321cf37308d69df2',
                                'spanId'            => '051581bf3cb55c13',
                                'name'              => 'GET /orders',
                                'kind'              => 2,
                                'startTimeUnixNano' => '1660296023390000000',
                                'endTimeUnixNano'   => '1660296023420000000',
                                'status'            => ['code' => 1],
                                'attributes'        => [
                                    ['key' => 'http.method', 'value' => ['stringValue' => 'GET']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('a valid traces payload is accepted and queued for storage', function () {
    Queue::fake();

    $project = Project::factory()->create();

    $this->postJson(route('otel.traces'), otlpTracesPayload(), [
        'Authorization' => "Bearer {$project->token}",
    ])->assertOk();

    Queue::assertPushed(StoreOtlpSpans::class, fn (StoreOtlpSpans $job): bool => $job->projectId === $project->id
        && $job->payload                                                                         === otlpTracesPayload());
});

test('a traces payload missing "resourceSpans" is rejected', function () {
    Queue::fake();

    $project = Project::factory()->create();

    $this->postJson(route('otel.traces'), ['nope' => true], [
        'Authorization' => "Bearer {$project->token}",
    ])->assertStatus(400);

    Queue::assertNotPushed(StoreOtlpSpans::class);
});

test('a non-JSON traces request is rejected', function () {
    $project = Project::factory()->create();

    $this->post(route('otel.traces'), [], [
        'Authorization' => "Bearer {$project->token}",
        'Content-Type'  => 'application/x-protobuf',
    ])->assertStatus(415);
});

test('the store spans job inserts one row per span', function () {
    $project = Project::factory()->create();

    (new StoreOtlpSpans($project->id, otlpTracesPayload()))->handle();

    $this->assertDatabaseHas('otel_spans', [
        'project_id' => $project->id,
        'trace_id'   => '5b8aa5a2d2c872e8321cf37308d69df2',
        'span_id'    => '051581bf3cb55c13',
        'name'       => 'GET /orders',
    ]);
});

test('a span is categorized from its "isoxen.type" attribute', function () {
    $project                                                                  = Project::factory()->create();
    $payload                                                                  = otlpTracesPayload();
    $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['attributes'][] = [
        'key'   => 'isoxen.type',
        'value' => ['stringValue' => 'request'],
    ];

    (new StoreOtlpSpans($project->id, $payload))->handle();

    $this->assertDatabaseHas('otel_spans', [
        'project_id' => $project->id,
        'span_id'    => '051581bf3cb55c13',
        'type'       => 'request',
    ]);
});

test('a span without "isoxen.type" is stored as uncategorized', function () {
    $project = Project::factory()->create();

    (new StoreOtlpSpans($project->id, otlpTracesPayload()))->handle();

    $this->assertDatabaseHas('otel_spans', [
        'project_id' => $project->id,
        'span_id'    => '051581bf3cb55c13',
        'type'       => null,
    ]);
});
