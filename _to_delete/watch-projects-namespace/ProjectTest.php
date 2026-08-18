<?php

use App\Auth\Models\User;
use App\Watch\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// --- Guests ---

test('guests are redirected to the login page from every project route', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.index'))->assertRedirect(route('login'));
    $this->post(route('projects.store'), ['name' => 'My website'])->assertRedirect(route('login'));
    $this->get(route('projects.show', $project))->assertRedirect(route('login'));
    $this->put(route('projects.update', $project), ['name' => 'Renamed'])->assertRedirect(route('login'));
    $this->delete(route('projects.destroy', $project))->assertRedirect(route('login'));
});

// --- Listing ---

test('the project list only contains the authenticated user\'s projects', function () {
    $user = User::factory()->create();
    $ownProject = Project::factory()->for($user)->create();
    Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 1)
            ->where('projects.0.id', $ownProject->id)
        );
});

test('the project list does not expose the ingestion token', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.0.token', null)
        );
});

// --- Creating ---

test('a project can be created', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('projects.store'), [
        'name' => 'My website',
    ]);

    $project = $user->projects()->sole();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('projects.show', $project));

    expect($project->name)->toBe('My website');
    expect($project->slug)->not->toBeEmpty();
    expect($project->token)->toStartWith('proj_');
    expect($project->user_id)->toBe($user->id);
});

test('a project name is required to create a project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($user->projects()->count())->toBe(0);
});

test('a project name cannot exceed 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => str_repeat('a', 256)])
        ->assertSessionHasErrors('name');
});

// --- Showing ---

test('an owner can view their project, including its ingestion token', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('project.id', $project->id)
            ->where('project.token', $project->token)
        );
});

test('a user cannot view another user\'s project', function () {
    $project = Project::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('projects.show', $project))
        ->assertForbidden();
});

// --- Updating ---

test('an owner can update their project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->put(route('projects.update', $project), [
            'name' => 'Renamed project',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->name)->toBe('Renamed project');
});

test('a project name is required to update a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a user cannot update another user\'s project', function () {
    $project = Project::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->put(route('projects.update', $project), ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($project->fresh()->name)->toBe($project->name);
});

// --- Deleting ---

test('an owner can delete their project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('projects.destroy', $project));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('projects.index'));

    expect(Project::find($project->id))->toBeNull();
});

test('a user cannot delete another user\'s project', function () {
    $project = Project::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->delete(route('projects.destroy', $project))
        ->assertForbidden();

    expect(Project::find($project->id))->not->toBeNull();
});
