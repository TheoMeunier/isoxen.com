<?php

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeRequestSpan(int $projectId, string $name, int $durationMs, int $statusCode = 0): void
{
    DB::table('otel_spans')->insert([
        'project_id'     => $projectId,
        'trace_id'       => bin2hex(random_bytes(16)),
        'span_id'        => bin2hex(random_bytes(8)),
        'name'           => $name,
        'type'           => 'request',
        'kind'           => 2,
        'time'           => now(),
        'end_time'       => now()->addMilliseconds($durationMs),
        'duration_nanos' => $durationMs * 1_000_000,
        'status_code'    => $statusCode,
        'created_at'     => now(),
    ]);
}

test('the requests category includes a slow endpoints breakdown grouped by name', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeRequestSpan($project->id, 'GET /orders', 50);
    makeRequestSpan($project->id, 'GET /orders', 100);
    makeRequestSpan($project->id, 'GET /orders', 900, statusCode: 2);
    makeRequestSpan($project->id, 'GET /users', 10);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('slowEndpoints', 2)
            // Sorted by p95 descending -- the slower endpoint comes first.
            ->where('slowEndpoints.0.name', 'GET /orders')
            ->where('slowEndpoints.0.total', 3)
            ->where('slowEndpoints.0.errors', 1)
            ->where('slowEndpoints.1.name', 'GET /users')
            ->where('slowEndpoints.1.total', 1)
            ->where('slowEndpoints.1.errors', 0));
});

test('the slow endpoints breakdown is empty outside the requests category', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeRequestSpan($project->id, 'GET /orders', 50);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'queries']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('slowEndpoints', 0));
});
