<?php

use App\Watch\Ingestion\Models\OtelLog;
use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it only deletes telemetry older than each project\'s retention window', function () {
    $shortLived = Project::factory()->create(['retention_days' => 7]);
    $longLived  = Project::factory()->create(['retention_days' => 90]);

    $stale  = OtelLog::factory()->for($shortLived)->create(['time' => now()->subDays(30)]);
    $recent = OtelLog::factory()->for($shortLived)->create(['time' => now()->subDay()]);
    $kept   = OtelSpan::factory()->for($longLived)->create(['time' => now()->subDays(30)]);

    $this->artisan('isoxen:prune-telemetry')->assertSuccessful();

    expect(OtelLog::query()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(OtelLog::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(OtelSpan::query()->whereKey($kept->id)->exists())->toBeTrue();
});
