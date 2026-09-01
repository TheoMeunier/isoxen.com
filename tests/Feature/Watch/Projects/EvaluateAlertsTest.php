<?php

use App\Notifications\ErrorRateAlert;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeRequestSpans(int $projectId, int $successful, int $failed): void
{
    $rows = [];

    foreach ([...array_fill(0, $successful, 200), ...array_fill(0, $failed, 500)] as $status) {
        $rows[] = [
            'project_id'     => $projectId,
            'trace_id'       => bin2hex(random_bytes(16)),
            'span_id'        => bin2hex(random_bytes(8)),
            'name'           => 'GET /orders',
            'type'           => 'request',
            'time'           => now()->subMinutes(5),
            'duration_nanos' => 5_000_000,
            'status_code'    => 0,
            'attributes'     => json_encode(['http.response.status_code' => $status]),
            'created_at'     => now(),
        ];
    }

    DB::table('otel_spans')->insert($rows);
}

it('notifies the owner when the error rate crosses the threshold', function (): void {
    Notification::fake();

    $project = Project::factory()->create(['alert_error_rate_threshold' => 10]);
    makeRequestSpans($project->id, successful: 40, failed: 10);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    Notification::assertSentTo($project->user, ErrorRateAlert::class);
    expect($project->fresh()->alert_notified_at)->not->toBeNull();
});

it('stays quiet during the cooldown', function (): void {
    Notification::fake();

    $project = Project::factory()->create([
        'alert_error_rate_threshold' => 10,
        'alert_notified_at'          => now()->subMinutes(5),
    ]);
    makeRequestSpans($project->id, successful: 40, failed: 10);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    Notification::assertNothingSent();
});

it('stays quiet below the threshold, below the volume floor, or with alerting off', function (array $attributes, int $successful, int $failed): void {
    Notification::fake();

    $project = Project::factory()->create($attributes);
    makeRequestSpans($project->id, $successful, $failed);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    Notification::assertNothingSent();
})->with([
    'below the threshold' => [['alert_error_rate_threshold' => 50], 40, 10],
    'too few requests'    => [['alert_error_rate_threshold' => 10], 5, 5],
    'alerting disabled'   => [['alert_error_rate_threshold' => null], 40, 10],
]);

it('builds a mail naming the project, the rate and a link back to it', function (): void {
    Notification::fake();

    $project = Project::factory()->create([
        'name'                       => 'Checkout API',
        'alert_error_rate_threshold' => 10,
    ]);
    makeRequestSpans($project->id, successful: 40, failed: 10);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    Notification::assertSentTo($project->user, ErrorRateAlert::class, function (ErrorRateAlert $notification) use ($project): bool {
        $mail = $notification->toMail($project->user);

        expect($mail->subject)->toBe('[Checkout API] 20.0% of requests are failing')
            ->and($mail->level)->toBe('error')
            ->and($mail->introLines[0])->toContain("20.0% of the last hour's 50 requests")
            ->and($mail->introLines[0])->toContain('above the 10% threshold')
            ->and($mail->actionText)->toBe('Open requests')
            ->and($mail->actionUrl)->toBe(route('projects.show', [
                'project'  => $project,
                'category' => 'requests',
            ]));

        // Rendering catches a broken Blade/route reference the assertions above
        // would miss. The project name lives in the subject, not the body, so
        // the rendered check looks for the line and the link instead.
        $html = (string) $mail->render();

        expect($html)->toContain("20.0% of the last hour's 50 requests")
            ->and($html)->toContain($mail->actionUrl);

        return true;
    });
});

it('is queued rather than sent inline', function (): void {
    expect(new ErrorRateAlert(Project::factory()->make(), 20.0, 50))
        ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});
