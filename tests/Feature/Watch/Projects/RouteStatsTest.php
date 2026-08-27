<?php

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeRequestSpan(int $projectId, string $name, int $durationMs, ?int $statusCode = 200): void
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
        'status_code'    => $statusCode !== null && $statusCode >= 500 ? 2 : 0,
        'attributes'     => $statusCode === null ? null : json_encode(['http.response.status_code' => $statusCode]),
        'created_at'     => now(),
    ]);
}

test('the requests category includes a route breakdown grouped by path', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeRequestSpan($project->id, 'GET /orders', 50, 200);
    makeRequestSpan($project->id, 'GET /orders', 100, 200);
    makeRequestSpan($project->id, 'GET /orders', 900, 500);
    makeRequestSpan($project->id, 'GET /users', 10, 404);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('routes', 2)
            // Sorted by total descending -- the busier route comes first.
            ->where('routes.0.path', '/orders')
            ->where('routes.0.methods', 'GET')
            ->where('routes.0.total', 3)
            ->where('routes.0.success', 2)
            ->where('routes.0.client_error', 0)
            ->where('routes.0.server_error', 1)
            ->where('routes.1.path', '/users')
            ->where('routes.1.total', 1)
            ->where('routes.1.client_error', 1));
});

test('a path hit by more than one method shows every method it was seen with', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeRequestSpan($project->id, 'GET /posts/1', 20);
    makeRequestSpan($project->id, 'HEAD /posts/1', 5);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('routes', 1)
            ->where('routes.0.path', '/posts/1')
            ->where('routes.0.methods', 'GET|HEAD'));
});

test('the route breakdown is empty outside the requests category', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeRequestSpan($project->id, 'GET /orders', 50);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'queries']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('routes', 0));
});
