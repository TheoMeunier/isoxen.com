<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RecentMetricsQuery
{
    public function execute(Project $project, int $perPage = 25): LengthAwarePaginator
    {
        return DB::table('otel_metrics')
            ->where('project_id', $project->id)
            ->orderByDesc('time')
            ->select(['time', 'name', 'type', 'unit', 'value'])
            ->paginate($perPage)
            ->withQueryString();
    }
}
