<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

class SpanTypeCountsQuery
{
    /**
     * Count spans per `type`, for the sidebar's badge counts.
     *
     * @return array<string, int> keyed by `type`; null-typed spans are omitted
     */
    public function execute(Project $project): array
    {
        return DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->whereNotNull('type')
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }
}
