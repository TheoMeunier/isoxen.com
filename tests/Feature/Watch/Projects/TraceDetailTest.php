<?php

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

const TRACE_ID       = '5b8aa5a2d2c872e8321cf37308d69df2';
const OTHER_TRACE_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

function makeTraceSpan(int $projectId, string $spanId, ?string $parentSpanId, string $name, string $type, string $traceId = TRACE_ID): void
{
    DB::table('otel_spans')->insert([
        'project_id'     => $projectId,
        'trace_id'       => $traceId,
        'span_id'        => $spanId,
        'parent_span_id' => $parentSpanId,
        'name'           => $name,
        'type'           => $type,
        'kind'           => 1,
        'time'           => now(),
        'end_time'       => now()->addMilliseconds(10),
        'duration_nanos' => 10_000_000,
        'status_code'    => 0,
        'attributes'     => json_encode(['db.query.text' => 'select 1']),
        'created_at'     => now(),
    ]);
}

test('the trace page shows every span that belongs to the trace, in order', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeTraceSpan($project->id, 'root', null, 'GET /orders', 'request');
    makeTraceSpan($project->id, 'child', 'root', 'select * from orders', 'query');
    // A span from a different trace must never show up here.
    makeTraceSpan($project->id, 'unrelated', null, 'GET /other', 'request', OTHER_TRACE_ID);

    $this->actingAs($user)
        ->get(route('projects.traces.show', [$project, TRACE_ID]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('traceId', TRACE_ID)
            ->has('spans', 2)
            ->where('spans.0.span_id', 'root')
            ->where('spans.1.span_id', 'child')
            ->where('spans.1.parent_span_id', 'root'));
});

test('the trace page includes log lines correlated to the trace', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    makeTraceSpan($project->id, 'root', null, 'GET /orders', 'request');

    DB::table('otel_logs')->insert([
        'project_id'      => $project->id,
        'trace_id'        => TRACE_ID,
        'span_id'         => 'root',
        'severity_text'   => 'ERROR',
        'severity_number' => 17,
        'body'            => 'Something went wrong',
        'time'            => now(),
        'created_at'      => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.traces.show', [$project, TRACE_ID]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 1)
            ->where('logs.0.body', 'Something went wrong'));
});

test('an unknown trace id returns a 404', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.traces.show', [$project, TRACE_ID]))
        ->assertNotFound();
});

test('a user cannot view a trace from another user\'s project', function () {
    $owner    = User::factory()->create();
    $intruder = User::factory()->create();
    $project  = Project::factory()->for($owner)->create();

    makeTraceSpan($project->id, 'root', null, 'GET /orders', 'request');

    $this->actingAs($intruder)
        ->get(route('projects.traces.show', [$project, TRACE_ID]))
        ->assertForbidden();
});

test('span timestamps keep sub-second precision through storage and the response', function () {
    // Two spans a few milliseconds apart, both within the same second --
    // a regression test for a bug where both the raw insert and the ISO
    // formatting used for the response silently rounded down to whole
    // seconds, making every span in a fast trace look simultaneous.
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $base    = now()->startOfSecond();

    DB::table('otel_spans')->insert([
        'project_id'     => $project->id,
        'trace_id'       => TRACE_ID,
        'span_id'        => 'root',
        'parent_span_id' => null,
        'name'           => 'GET /orders',
        'type'           => 'request',
        'kind'           => 1,
        'time'           => $base->copy()->format('Y-m-d H:i:s.u'),
        'end_time'       => $base->copy()->addMilliseconds(300)->format('Y-m-d H:i:s.u'),
        'duration_nanos' => 300_000_000,
        'status_code'    => 0,
        'attributes'     => json_encode([]),
        'created_at'     => now(),
    ]);

    DB::table('otel_spans')->insert([
        'project_id'     => $project->id,
        'trace_id'       => TRACE_ID,
        'span_id'        => 'child',
        'parent_span_id' => 'root',
        'name'           => 'select * from orders',
        'type'           => 'query',
        'kind'           => 1,
        'time'           => $base->copy()->addMilliseconds(150)->format('Y-m-d H:i:s.u'),
        'end_time'       => $base->copy()->addMilliseconds(200)->format('Y-m-d H:i:s.u'),
        'duration_nanos' => 50_000_000,
        'status_code'    => 0,
        'attributes'     => json_encode([]),
        'created_at'     => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.traces.show', [$project, TRACE_ID]))
        ->assertInertia(function (Assert $page) use ($base) {
            $page->has('spans', 2)
                ->where('spans.0.time', fn (string $time) => Carbon::parse($time)->eq($base))
                ->where('spans.1.time', fn (string $time) => Carbon::parse($time)->eq($base->copy()->addMilliseconds(150)));
        });
});
