<?php

use App\Auth\Models\User;
use App\Watch\Ingestion\Queries\ExceptionGroupsQuery;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeExceptionSpan(
    int $projectId,
    string $type,
    ?string $message = 'Something went wrong',
    ?string $file = '/app/Http/Controllers/OrderController.php',
    ?int $line = 42,
    ?string $time = null,
    ?int $httpStatus = null,
): void {
    $attributes = [
        'exception.type'    => $type,
        'exception.message' => $message,
        'code.file.path'    => $file,
        'code.line.number'  => $line,
    ];

    if ($httpStatus !== null) {
        $attributes['http.response.status_code'] = $httpStatus;
    }

    DB::table('otel_spans')->insert([
        'project_id' => $projectId,
        'trace_id'   => bin2hex(random_bytes(16)),
        'span_id'    => bin2hex(random_bytes(8)),
        'name'       => class_basename($type),
        'type'       => 'exception',
        'kind'       => 1,
        'time'       => $time ?? now(),
        'attributes' => json_encode($attributes),
        'created_at' => now(),
    ]);
}

test('the exceptions category groups occurrences by type, file and line', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeExceptionSpan($project->id, 'RuntimeException', time: now()->subMinutes(30));
    makeExceptionSpan($project->id, 'RuntimeException', time: now()->subMinutes(10));
    makeExceptionSpan($project->id, 'LogicException', file: '/app/Jobs/SyncJob.php', line: 7, time: now()->subMinutes(20));

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'exceptions']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('exceptionGroups', 2)
            // Sorted by last seen descending.
            ->where('exceptionGroups.0.type', 'RuntimeException')
            ->where('exceptionGroups.0.occurrences', 2)
            ->where('exceptionGroups.1.type', 'LogicException')
            ->where('exceptionGroups.1.occurrences', 1));
});

test('exceptions without a file and line still group by type and message', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeExceptionSpan($project->id, 'RuntimeException', message: 'Queue timed out', file: null, line: null);
    makeExceptionSpan($project->id, 'RuntimeException', message: 'Queue timed out', file: null, line: null);
    makeExceptionSpan($project->id, 'RuntimeException', message: 'Different failure', file: null, line: null);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'exceptions']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('exceptionGroups', 2));
});

test('the exception groups are empty outside the exceptions category', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeExceptionSpan($project->id, 'RuntimeException');

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'queries']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('exceptionGroups', 0));
});

test('clicking through to an exception shows its full detail', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeExceptionSpan($project->id, 'RuntimeException', time: now()->subDays(2));
    makeExceptionSpan($project->id, 'RuntimeException', time: now()->subMinutes(5));

    $fingerprint = app(ExceptionGroupsQuery::class)->execute($project, hours: 24 * 30)->first()->fingerprint;

    $this->actingAs($user)
        ->get(route('projects.exceptions.show', ['project' => $project, 'fingerprint' => $fingerprint]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show/exception')
            ->where('exception.type', 'RuntimeException')
            ->where('exception.occurrences_30d', 2)
            ->where('exception.occurrences_24h', 1));
});

test('the http status code flows through to the list and the detail page', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeExceptionSpan($project->id, 'Illuminate\Http\Client\RequestException', file: '/app/Http/Controllers/FlightController.php', line: 347, httpStatus: 409);

    $this->actingAs($user)
        ->get(route('projects.show', ['project' => $project, 'category' => 'exceptions']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('exceptionGroups', 1)
            ->where('exceptionGroups.0.http_status', 409));

    $fingerprint = app(ExceptionGroupsQuery::class)->execute($project)->first()->fingerprint;

    $this->actingAs($user)
        ->get(route('projects.exceptions.show', ['project' => $project, 'fingerprint' => $fingerprint]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('exception.http_status', 409));
});

test('an unknown fingerprint 404s', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.exceptions.show', ['project' => $project, 'fingerprint' => str_repeat('a', 64)]))
        ->assertNotFound();
});
