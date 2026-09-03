<?php

use App\Auth\Models\User;
use App\Watch\Ingestion\Queries\ProjectHealthOverviewQuery;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @param  array<string, mixed>|null  $attributes
 */
function insertOverviewSpan(int $projectId, string $type, int $statusCode, ?array $attributes = null): void
{
    DB::table('otel_spans')->insert([
        'project_id'  => $projectId,
        'trace_id'    => bin2hex(random_bytes(16)),
        'span_id'     => bin2hex(random_bytes(8)),
        'name'        => 'span',
        'type'        => $type,
        'time'        => now(),
        'status_code' => $statusCode,
        'attributes'  => $attributes === null ? null : json_encode($attributes),
        'created_at'  => now(),
    ]);
}

function insertOverviewLog(int $projectId, int $severityNumber): void
{
    DB::table('otel_logs')->insert([
        'project_id'       => $projectId,
        'severity_number'  => $severityNumber,
        'time'             => now(),
        'created_at'       => now(),
    ]);
}

test('an owner can view their project overview', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'overview']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/overview')
            ->where('project.id', $project->id)
            ->has('categories')
            ->has('onlineUsers'));
});

test('a user cannot view another user\'s project overview', function () {
    $project  = Project::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('projects.show', ['project' => $project, 'category' => 'overview']))
        ->assertForbidden();
});

test('the health overview counts each category using its own error definition', function () {
    $project = Project::factory()->create();

    // Requests: only a 5XX response counts as an error -- a 404 is the caller's fault, not the app's.
    insertOverviewSpan($project->id, 'request', 1, ['http.response.status_code' => 200]);
    insertOverviewSpan($project->id, 'request', 1, ['http.response.status_code' => 404]);
    insertOverviewSpan($project->id, 'request', 2, ['http.response.status_code' => 500]);

    // A generic span category: the OTEL status code decides.
    insertOverviewSpan($project->id, 'job', 1);
    insertOverviewSpan($project->id, 'job', 2);

    // Exceptions: every row is an error, regardless of its OTEL status code.
    insertOverviewSpan($project->id, 'exception', 0);

    // Logs: severity Error (17) and above counts.
    insertOverviewLog($project->id, 9);
    insertOverviewLog($project->id, 17);

    $overview = app(ProjectHealthOverviewQuery::class)->execute($project);
    $bySlug   = collect($overview)->keyBy('slug');

    expect($bySlug['requests']['total'])->toBe(3);
    expect($bySlug['requests']['errors'])->toBe(1);

    expect($bySlug['jobs']['total'])->toBe(2);
    expect($bySlug['jobs']['errors'])->toBe(1);

    expect($bySlug['exceptions']['total'])->toBe(1);
    expect($bySlug['exceptions']['errors'])->toBe(1);

    expect($bySlug['logs']['total'])->toBe(2);
    expect($bySlug['logs']['errors'])->toBe(1);

    expect($bySlug['metrics']['total'])->toBe(0);
    expect($bySlug['metrics']['errors'])->toBe(0);

    expect(collect($overview)->pluck('slug'))->not->toContain('users');
});

test('a category with no data is reported as clean', function () {
    $project = Project::factory()->create();

    $overview = app(ProjectHealthOverviewQuery::class)->execute($project);
    $bySlug   = collect($overview)->keyBy('slug');

    expect($bySlug['commands']['total'])->toBe(0);
    expect($bySlug['commands']['errors'])->toBe(0);
});
