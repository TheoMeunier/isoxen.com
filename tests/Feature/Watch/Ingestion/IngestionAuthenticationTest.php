<?php

use App\Watch\Projects\Models\Project;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a request without an ingestion token is rejected', function () {
    $this->postJson(route('otel.traces'), ['resourceSpans' => []])
        ->assertUnauthorized();
});

test('a request with an unknown ingestion token is rejected', function () {
    $this->postJson(route('otel.traces'), ['resourceSpans' => []], [
        'Authorization' => 'Bearer not-a-real-token',
    ])->assertUnauthorized();
});

test('a request with a valid ingestion token is accepted', function () {
    $project = Project::factory()->create();

    $this->postJson(route('otel.traces'), ['resourceSpans' => []], [
        'Authorization' => "Bearer {$project->token}",
    ])->assertOk();
});
