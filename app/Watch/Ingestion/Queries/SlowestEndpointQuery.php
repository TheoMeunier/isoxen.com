<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\Date;

/**
 * The single slowest endpoint in a project over the last 24h, by average duration -- a cheap one-query
 * "what to look at first" callout for the dashboard. {@see SlowEndpointsQuery} does the same job with
 * full percentile breakdowns for every endpoint on the Requests page; that's too expensive to run once
 * per project on a page that already summarizes every project at once.
 */
class SlowestEndpointQuery
{
    /**
     * @return array{name: string, avgMs: float, total: int}|null
     */
    public function execute(Project $project, int $hours = 24): ?array
    {
        $since = Date::now()->subHours($hours);

        $row = OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->whereNotNull('name')
            ->selectRaw('name, COUNT(*) as total, AVG(duration_nanos) as avg_nanos')
            ->groupBy('name')
            ->orderByDesc('avg_nanos')
            ->limit(1)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'name'  => (string) $row->name,
            'avgMs' => round((float) $row->avg_nanos / 1_000_000, 1),
            'total' => (int) $row->total,
        ];
    }
}
