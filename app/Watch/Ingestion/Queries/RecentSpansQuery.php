<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RecentSpansQuery
{
    public function execute(Project $project, ?string $type = null, int $perPage = 25): LengthAwarePaginator
    {
        return DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('time')
            ->select(['time', 'name', 'type', 'kind', 'duration_nanos', 'status_code', 'trace_id', 'span_id'])
            ->paginate($perPage)
            ->withQueryString();
    }
}
