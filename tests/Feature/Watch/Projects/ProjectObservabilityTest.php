<?php

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeSpan(int $projectId, ?string $type, string $name = 'span'): void
{
    DB::table('otel_spans')->insert([
        'project_id' => $projectId,
        'trace_id'   => '5b8aa5a2d2c872e8321cf37308d69df2',
        'span_id'    => '051581bf3cb55c13',
        'name'       => $name,
        'type'       => $type,
        'time'       => now(),
        'created_at' => now(),
    ]);
}

test('the project page defaults to the requests category', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    makeSpan($project->id, 'request', 'GET /orders');
    makeSpan($project->id, 'query', 'select * from orders');

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeCategory', 'requests')
            ->has('entries.data', 1)
            ->where('entries.data.0.name', 'GET /orders'));
});

test('switching category filters spans by their type', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    makeSpan($project->id, 'request', 'GET /orders');
    makeSpan($project->id, 'query', 'select * from orders');

    $this->actingAs($user)
        ->get(route('projects.show', $project, ['category' => 'queries']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeCategory', 'queries')
            ->has('entries.data', 1)
            ->where('entries.data.0.name', 'select * from orders'));
});

test('the queries category exposes the full SQL as detail', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    DB::table('otel_spans')->insert([
        'project_id' => $project->id,
        'trace_id'   => '5b8aa5a2d2c872e8321cf37308d69df2',
        'span_id'    => '051581bf3cb55c13',
        'name'       => 'SELECT',
        'type'       => 'query',
        'time'       => now(),
        'attributes' => json_encode([
            'db.query.text' => 'select * from `orders` where `id` = ?',
        ]),
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', [$project, 'category' => 'queries']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.name', 'SELECT')
            ->where('entries.data.0.detail', 'select * from `orders` where `id` = ?'));
});

test('the outgoing requests category exposes the full URL as detail', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    DB::table('otel_spans')->insert([
        'project_id' => $project->id,
        'trace_id'   => '5b8aa5a2d2c872e8321cf37308d69df2',
        'span_id'    => '051581bf3cb55c13',
        'name'       => 'GET',
        'type'       => 'outgoing_request',
        'time'       => now(),
        'attributes' => json_encode([
            'http.request.method' => 'GET',
            'url.full'            => 'https://api.stripe.com/v1/charges?limit=10',
        ]),
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', [$project, 'category' => 'outgoing-requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.detail', 'GET https://api.stripe.com/v1/charges?limit=10'));
});

test('the cache category exposes the operation and key as detail', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    DB::table('otel_spans')->insert([
        'project_id' => $project->id,
        'trace_id'   => '5b8aa5a2d2c872e8321cf37308d69df2',
        'span_id'    => '051581bf3cb55c13',
        'name'       => 'cache hit',
        'type'       => 'cache',
        'time'       => now(),
        'attributes' => json_encode([
            'cache.operation' => 'hit',
            'cache.key'       => 'orders:by-customer:42',
        ]),
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', [$project, 'category' => 'cache']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.name', 'cache hit')
            ->where('entries.data.0.detail', 'hit orders:by-customer:42'));
});

test('other categories never expose a detail field', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    makeSpan($project->id, 'request', 'GET /orders');

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('entries.data.0.detail'));
});

test('an unknown category falls back to requests', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project, ['category' => 'not-a-real-category']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeCategory', 'requests'));
});

test('the information tab skips the activity pipeline entirely', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    makeSpan($project->id, 'request', 'GET /orders');

    $this->actingAs($user)
        ->get(route('projects.show', [$project, 'category' => 'information']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeCategory', 'information')
            ->has('entries.data', 0)
            ->where('summary.total', 0)
            ->has('timeline', 0)
            ->has('durationTimeline', 0)
            ->has('statusBreakdown', 0)
            ->has('statusTimeline', 0)
            ->has('slowEndpoints', 0));
});

test('the users category reports who is currently online', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    DB::table('otel_spans')->insert([
        'project_id' => $project->id,
        'trace_id'   => '5b8aa5a2d2c872e8321cf37308d69df2',
        'span_id'    => '051581bf3cb55c13',
        'name'       => 'user login',
        'type'       => 'user',
        'time'       => now(),
        'attributes' => json_encode([
            'enduser.id'             => '42',
            'user.email'             => 'jane@example.com',
            'isoxen.user.operation'  => 'login',
        ]),
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', [$project, 'category' => 'users']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('onlineUsers', 1)
            ->where('onlineUsers.0.id', '42')
            ->where('onlineUsers.0.email', 'jane@example.com'));
});

test('a user is only online if their most recent event is a login', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    // CarbonInterface, not Carbon: the app configures Date::use(CarbonImmutable::class)
    // (see AppServiceProvider), so now()->subMinutes(...) below may hand back
    // either Carbon or CarbonImmutable depending on boot order.
    $insert = function (string $id, string $operation, Carbon\CarbonInterface $time) use ($project): void {
        DB::table('otel_spans')->insert([
            'project_id' => $project->id,
            'trace_id'   => bin2hex(random_bytes(16)),
            'span_id'    => bin2hex(random_bytes(8)),
            'name'       => "user {$operation}",
            'type'       => 'user',
            'time'       => $time,
            'attributes' => json_encode([
                'enduser.id'            => $id,
                'isoxen.user.operation' => $operation,
            ]),
            'created_at' => now(),
        ]);
    };

    // Logged in, then out: not online.
    $insert('1', 'login', now()->subMinutes(10));
    $insert('1', 'logout', now()->subMinutes(5));

    // Logged out, then back in: online, even though an older event says
    // otherwise -- the query has to key off the most recent event per
    // user, not just whichever row it sees.
    $insert('2', 'login', now()->subMinutes(10));
    $insert('2', 'logout', now()->subMinutes(8));
    $insert('2', 'login', now()->subMinutes(1));

    $this->actingAs($user)
        ->get(route('projects.show', [$project, 'category' => 'users']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('onlineUsers', 1)
            ->where('onlineUsers.0.id', '2'));
});

test('other categories do not compute who is online', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    makeSpan($project->id, 'request', 'GET /orders');

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->has('onlineUsers', 0));
});

test('the sidebar counts are grouped by span type', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    makeSpan($project->id, 'request', 'GET /a');
    makeSpan($project->id, 'request', 'GET /b');
    makeSpan($project->id, 'query', 'select 1');
    makeSpan($project->id, null, 'uncategorized');

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categoryCounts.request', 2)
            ->where('categoryCounts.query', 1));
});
