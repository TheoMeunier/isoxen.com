<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\ReturnsIsoTimestamps;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraceLogsQuery
{
    use ReturnsIsoTimestamps;

    /**
     * @return Collection<int, object>
     */
    public function execute(Project $project, string $traceId, int $limit = 200): Collection
    {
        return DB::table('otel_logs')
            ->where('project_id', $project->id)
            ->where('trace_id', $traceId)
            ->orderBy('time')
            ->select(['time', 'span_id', 'severity_text', 'severity_number', 'body'])
            ->limit($limit)
            ->get()
            ->map(fn (object $row): object => $this->toIso($row));
    }
}
