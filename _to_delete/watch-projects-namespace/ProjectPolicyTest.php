<?php

use App\Auth\Models\User;
use App\Watch\Models\Project;
use App\Watch\Policies\ProjectPolicy;

test('any authenticated user can list their projects and create new ones', function () {
    $policy = new ProjectPolicy();
    $user = User::factory()->make();

    expect($policy->viewAny($user))->toBeTrue();
    expect($policy->create($user))->toBeTrue();
});

test('only the owner can view, update or delete their project', function () {
    $policy = new ProjectPolicy();

    $owner = User::factory()->make(['id' => 1]);
    $intruder = User::factory()->make(['id' => 2]);
    $project = Project::factory()->make(['user_id' => 1]);

    expect($policy->view($owner, $project))->toBeTrue();
    expect($policy->update($owner, $project))->toBeTrue();
    expect($policy->delete($owner, $project))->toBeTrue();

    expect($policy->view($intruder, $project))->toBeFalse();
    expect($policy->update($intruder, $project))->toBeFalse();
    expect($policy->delete($intruder, $project))->toBeFalse();
});
