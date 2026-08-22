<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class RecentMetricsQuery
{
    public function execute(Project $project, int $perPage = 25): LengthAwarePaginator
    {
        return $project->metrics()
            ->orderByDesc('time')
            ->select(['time', 'name', 'type', 'unit', 'value'])
            ->paginate($perPage)
            ->withQueryString();
    }
}
