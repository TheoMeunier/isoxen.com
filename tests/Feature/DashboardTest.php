<?php

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function insertDashboardRequestSpan(int $projectId, int $httpStatus): void
{
    DB::table('otel_spans')->insert([
        'project_id'  => $projectId,
        'trace_id'    => bin2hex(random_bytes(16)),
        'span_id'     => bin2hex(random_bytes(8)),
        'name'        => 'span',
        'type'        => 'request',
        'time'        => now(),
        'status_code' => $httpStatus >= 500 ? 2 : 1,
        'attributes'  => json_encode(['http.response.status_code' => $httpStatus]),
        'created_at'  => now(),
    ]);
}

function insertDashboardExceptionSpan(int $projectId, string $name, Carbon\CarbonInterface $time, ?string $statusMessage = null): void
{
    DB::table('otel_spans')->insert([
        'project_id'     => $projectId,
        'trace_id'       => bin2hex(random_bytes(16)),
        'span_id'        => bin2hex(random_bytes(8)),
        'name'           => $name,
        'type'           => 'exception',
        'time'           => $time,
        'status_code'    => 2,
        'status_message' => $statusMessage,
        'created_at'     => now(),
    ]);
}

function insertDashboardTimedRequestSpan(int $projectId, string $name, int $durationNanos): void
{
    DB::table('otel_spans')->insert([
        'project_id'     => $projectId,
        'trace_id'       => bin2hex(random_bytes(16)),
        'span_id'        => bin2hex(random_bytes(8)),
        'name'           => $name,
        'type'           => 'request',
        'time'           => now(),
        'status_code'    => 1,
        'duration_nanos' => $durationNanos,
        'attributes'     => json_encode(['http.response.status_code' => 200]),
        'created_at'     => now(),
    ]);
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('users with no projects see an empty dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('overview', null)
            ->has('projects', 0)
            ->has('projectStats', 0));
});

test('the dashboard sums requests and errors across every project the user owns', function () {
    $user     = User::factory()->create();
    $projectA = Project::factory()->for($user)->create(['name' => 'Alpha']);
    $projectB = Project::factory()->for($user)->create(['name' => 'Beta']);

    insertDashboardRequestSpan($projectA->id, 200);
    insertDashboardRequestSpan($projectA->id, 500);
    insertDashboardRequestSpan($projectB->id, 200);
    insertDashboardRequestSpan($projectB->id, 404);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('overview.projectsCount', 2)
            ->where('overview.requests24h', 4)
            ->where('overview.clientErrors24h', 1)
            ->where('overview.serverErrors24h', 1)
            ->where('overview.projectsOnline', 2)
            ->has('projects', 2)
            ->has('projectStats', 2));
});

test('a project belonging to another user is excluded from the dashboard', function () {
    $user      = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownProject   = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($otherUser)->create();

    insertDashboardRequestSpan($ownProject->id, 200);
    insertDashboardRequestSpan($otherProject->id, 200);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.projectsCount', 1)
            ->where('overview.requests24h', 1)
            ->has('projects', 1));
});

test('a project with server errors is listed before healthy ones, regardless of creation order', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['name' => 'Alpha']);
    $beta = Project::factory()->for($user)->create(['name' => 'Beta']);
    Project::factory()->for($user)->create(['name' => 'Gamma']);

    insertDashboardRequestSpan($beta->id, 500);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 3)
            ->where('projects.0.name', 'Beta'));
});

test('each project card surfaces its own slowest endpoint', function () {
    // created_at is set explicitly (rather than relying on factory-default "now") so ordering by
    // latest() is deterministic even if both rows land in the same second.
    $user     = User::factory()->create();
    $projectA = Project::factory()->for($user)->create(['name' => 'Alpha', 'created_at' => now()->subMinutes(2)]);
    $projectB = Project::factory()->for($user)->create(['name' => 'Beta', 'created_at' => now()->subMinute()]);

    insertDashboardTimedRequestSpan($projectA->id, 'GET /fast', 20_000_000);
    insertDashboardTimedRequestSpan($projectB->id, 'GET /slow', 900_500_000);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            // latest() orders Beta (created most recently) before Alpha.
            ->where('projectStats.0.slowestEndpoint.name', 'GET /slow')
            ->where('projectStats.0.slowestEndpoint.avgMs', 900.5)
            ->where('projectStats.1.slowestEndpoint.name', 'GET /fast'));
});

test('a project that has sent telemetry recently is marked online', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    insertDashboardRequestSpan($project->id, 200);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectStats.0.online', true)
            ->where('projectStats.0.lastSeenAt', fn (?string $value): bool => $value !== null));
});

test('a project with no telemetry at all is marked offline with no last-seen time', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectStats.0.online', false)
            ->where('projectStats.0.lastSeenAt', null)
            ->where('overview.projectsOnline', 0));
});

test('a project is marked offline once its last event falls outside the stale window', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    DB::table('otel_spans')->insert([
        'project_id'  => $project->id,
        'trace_id'    => bin2hex(random_bytes(16)),
        'span_id'     => bin2hex(random_bytes(8)),
        'name'        => 'span',
        'type'        => 'request',
        'time'        => now()->subMinutes(30),
        'status_code' => 1,
        'attributes'  => json_encode(['http.response.status_code' => 200]),
        'created_at'  => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectStats.0.online', false)
            ->where('projectStats.0.lastSeenAt', fn (?string $value): bool => $value !== null));
});

test('each project card surfaces its own latest exception, not another project\'s', function () {
    // created_at pins latest() ordering deterministically -- see the slowest-endpoint test above.
    $user     = User::factory()->create();
    $projectA = Project::factory()->for($user)->create(['name' => 'Alpha', 'created_at' => now()->subMinutes(2)]);
    $projectB = Project::factory()->for($user)->create(['name' => 'Beta', 'created_at' => now()->subMinute()]);

    insertDashboardExceptionSpan($projectA->id, 'OldException', now()->subHours(30), 'too old to show');
    insertDashboardExceptionSpan($projectA->id, 'StaleException', now()->subMinutes(30), 'older');
    insertDashboardExceptionSpan($projectB->id, 'FreshException', now()->subMinutes(5), 'newest');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            // projectStats.0 = Beta (latest()), projectStats.1 = Alpha.
            ->where('projectStats.0.latestException.name', 'FreshException')
            ->where('projectStats.0.latestException.statusMessage', 'newest')
            ->where('projectStats.1.latestException.name', 'StaleException'));
});

test('a project with no exceptions has no latest exception', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    insertDashboardRequestSpan($project->id, 200);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectStats.0.latestException', null));
});
