<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class RecentLogsQuery
{
    public function execute(Project $project, int $perPage = 25): LengthAwarePaginator
    {
        return $project->logs()
            ->orderByDesc('time')
            ->select(['time', 'severity_text', 'severity_number', 'body', 'trace_id', 'span_id'])
            ->paginate($perPage)
            ->withQueryString();
    }
}
