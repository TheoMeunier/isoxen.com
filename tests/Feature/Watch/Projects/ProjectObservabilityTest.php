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
        'trace_id' => '5b8aa5a2d2c872e8321cf37308d69df2',
        'span_id' => '051581bf3cb55c13',
        'name' => $name,
        'type' => $type,
        'time' => now(),
        'created_at' => now(),
    ]);
}

test('the project page defaults to the requests category', function () {
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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

test('an unknown category falls back to requests', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project, ['category' => 'not-a-real-category']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeCategory', 'requests'));
});

test('the sidebar counts are grouped by span type', function () {
    $user = User::factory()->create();
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
