<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\Date;

/**
 * The single most recent exception in a project over the last 24h -- the dashboard card's own
 * "what's on fire here" callout. One cheap indexed query per project, mirroring
 * {@see SlowestEndpointQuery}, so a noisy project elsewhere can never crowd a quieter project's own
 * exception out of *its own* card the way a single fleet-wide top-N list would.
 */
class LatestExceptionQuery
{
    /**
     * @return array{time: string, name: string|null, statusMessage: string|null, traceId: string|null}|null
     */
    public function execute(Project $project, int $hours = 24): ?array
    {
        $since = Date::now('UTC')->subHours($hours);

        /** @var OtelSpan|null $span */
        $span = $project->spans()
            ->where('type', 'exception')
            ->where('time', '>=', $since)
            ->orderByDesc('time')
            ->first(['time', 'name', 'status_message', 'trace_id']);

        if ($span === null) {
            return null;
        }

        return [
            'time'          => $span->time->toIso8601String(),
            'name'          => $span->name,
            'statusMessage' => $span->status_message,
            'traceId'       => $span->trace_id,
        ];
    }
}
