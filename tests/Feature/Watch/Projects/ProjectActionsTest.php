<?php

use App\Auth\Models\User;
use App\Watch\Projects\Actions\CreateProjectAction;
use App\Watch\Projects\Actions\DeleteProjectAction;
use App\Watch\Projects\Actions\UpdateProjectAction;
use App\Watch\Projects\Models\Project;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('CreateProjectAction creates a project owned by the given user with a generated slug and token', function () {
    $user = User::factory()->create();

    $project = (new CreateProjectAction)->execute($user, ['name' => 'My website']);

    expect($project->exists)->toBeTrue();
    expect($project->user_id)->toBe($user->id);
    expect($project->name)->toBe('My website');
    expect($project->slug)->toStartWith('my-website-');
    expect($project->token)->toStartWith('proj_');
});

test('CreateProjectAction generates a unique slug for projects sharing the same name', function () {
    $user   = User::factory()->create();
    $action = new CreateProjectAction;

    $first  = $action->execute($user, ['name' => 'My website']);
    $second = $action->execute($user, ['name' => 'My website']);

    expect($first->slug)->not->toBe($second->slug);
});

test('UpdateProjectAction updates the project name', function () {
    $project = Project::factory()->create(['name' => 'Old name']);

    (new UpdateProjectAction)->execute($project, ['name' => 'New name']);

    expect($project->fresh()->name)->toBe('New name');
});

test('DeleteProjectAction deletes the project', function () {
    $project = Project::factory()->create();

    (new DeleteProjectAction)->execute($project);

    expect(Project::find($project->id))->toBeNull();
});
