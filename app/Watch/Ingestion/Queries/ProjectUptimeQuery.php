<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * A simple "is telemetry still arriving" signal for the dashboard's uptime badge. isoxen has no
 * synthetic prober -- it only ever knows what a project chooses to report -- so this reads the most
 * recent span, log or metric timestamp received in the last 24h and calls the project online if that's
 * within the stale window. A genuinely healthy but quiet app (no requests, no background jobs) can
 * therefore read as offline; that tradeoff was chosen deliberately over building a real heartbeat/ping
 * system, and is why the badge reads "Offline" rather than something stronger like "Down".
 */
class ProjectUptimeQuery
{
    /**
     * @return array{online: bool, lastSeenAt: string|null}
     */
    public function execute(Project $project, int $hours = 24, int $staleAfterMinutes = 5): array
    {
        $lastSeen = $this->latestEventAt($project, $hours);

        if (!$lastSeen instanceof \Carbon\CarbonImmutable) {
            return ['online' => false, 'lastSeenAt' => null];
        }

        return [
            'online'     => $lastSeen->greaterThanOrEqualTo(Date::now('UTC')->subMinutes($staleAfterMinutes)),
            'lastSeenAt' => $lastSeen->toIso8601String(),
        ];
    }

    private function latestEventAt(Project $project, int $hours): ?CarbonImmutable
    {
        $since = Date::now('UTC')->subHours($hours);

        $timestamps = [
            $project->spans()->where('time', '>=', $since)->max('time'),
            $project->logs()->where('time', '>=', $since)->max('time'),
            $project->metrics()->where('time', '>=', $since)->max('time'),
        ];

        $parsed = collect($timestamps)
            ->filter()
            ->map(fn (string $time): CarbonImmutable => Date::parse($time, 'UTC'));

        return $parsed->isEmpty() ? null : $parsed->max();
    }
}
