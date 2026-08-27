<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The most recent exception spans across every project a user owns -- the dashboard's "what's on fire"
 * list. One query with a `whereIn`, rather than one query per project, since it only ever needs a
 * handful of rows.
 */
class RecentExceptionsQuery
{
    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{projectId: int, projectName: string, time: string, name: string|null, statusMessage: string|null, traceId: string|null}>
     */
    public function execute(Collection $projects, int $limit = 6, int $hours = 24): array
    {
        if ($projects->isEmpty()) {
            return [];
        }

        $since = Date::now()->subHours($hours);
        $names = $projects->pluck('name', 'id');

        return OtelSpan::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('type', 'exception')
            ->where('time', '>=', $since)
            ->orderByDesc('time')
            ->limit($limit)
            ->get(['project_id', 'time', 'name', 'status_message', 'trace_id'])
            ->map(fn (OtelSpan $span): array => [
                'projectId'     => $span->project_id,
                'projectName'   => (string) ($names[$span->project_id] ?? ''),
                'time'          => $span->time->toIso8601String(),
                'name'          => $span->name,
                'statusMessage' => $span->status_message,
                'traceId'       => $span->trace_id,
            ])
            ->all();
    }
}
