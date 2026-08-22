<?php

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeStatusSpan(int $projectId, string $type, int $statusCode, ?int $httpStatus = null): void
{
    DB::table('otel_spans')->insert([
        'project_id'     => $projectId,
        'trace_id'       => bin2hex(random_bytes(16)),
        'span_id'        => bin2hex(random_bytes(8)),
        'name'           => 'span',
        'type'           => $type,
        'time'           => now(),
        'duration_nanos' => 5_000_000,
        'status_code'    => $statusCode,
        'attributes'     => $httpStatus === null ? null : json_encode(['http.response.status_code' => $httpStatus]),
        'created_at'     => now(),
    ]);
}

test('requests breaks down by HTTP status class', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeStatusSpan($project->id, 'request', 1, httpStatus: 200);
    makeStatusSpan($project->id, 'request', 1, httpStatus: 301);
    makeStatusSpan($project->id, 'request', 2, httpStatus: 404);
    makeStatusSpan($project->id, 'request', 2, httpStatus: 500);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusBreakdown.0.label', '1XX-3XX')
            ->where('statusBreakdown.0.value', 2)
            ->where('statusBreakdown.1.label', '4XX')
            ->where('statusBreakdown.1.value', 1)
            ->where('statusBreakdown.2.label', '5XX')
            ->where('statusBreakdown.2.value', 1)
            ->has('durationTimeline', 24)
            ->has('statusTimeline', 24)
            ->where('statusTimeline.0.segments.0.label', '1XX-3XX')
            ->where('statusTimeline.0.segments.1.label', '4XX')
            ->where('statusTimeline.0.segments.2.label', '5XX'));
});

test('logs break down by severity', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    DB::table('otel_logs')->insert([
        ['project_id' => $project->id, 'severity_number' => 9, 'body' => 'info', 'time' => now(), 'created_at' => now()],
        ['project_id' => $project->id, 'severity_number' => 14, 'body' => 'warn', 'time' => now(), 'created_at' => now()],
        ['project_id' => $project->id, 'severity_number' => 17, 'body' => 'error', 'time' => now(), 'created_at' => now()],
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'logs']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusBreakdown.0.label', 'Info')
            ->where('statusBreakdown.0.value', 1)
            ->where('statusBreakdown.1.label', 'Warning')
            ->where('statusBreakdown.1.value', 1)
            ->where('statusBreakdown.2.label', 'Error')
            ->where('statusBreakdown.2.value', 1)
            ->has('durationTimeline', 0)
            ->has('statusTimeline', 24)
            ->where('statusTimeline.0.segments.0.label', 'Info')
            ->where('statusTimeline.0.segments.1.label', 'Warning')
            ->where('statusTimeline.0.segments.2.label', 'Error'));
});

test('a generic span category falls back to a success/failure split with its own labels', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeStatusSpan($project->id, 'command', 0);
    makeStatusSpan($project->id, 'command', 2);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'commands']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusBreakdown.0.label', 'Successful')
            ->where('statusBreakdown.0.value', 1)
            ->where('statusBreakdown.1.label', 'Unsuccessful')
            ->where('statusBreakdown.1.value', 1)
            ->has('statusTimeline', 24)
            ->where('statusTimeline.0.segments.0.label', 'Successful')
            ->where('statusTimeline.0.segments.1.label', 'Unsuccessful'));
});

test('metrics have no status breakdown or duration timeline', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'metrics']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('statusBreakdown', 0)
            ->has('durationTimeline', 0)
            ->has('statusTimeline', 0));
});
