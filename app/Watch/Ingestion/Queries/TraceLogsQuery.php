<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelLog;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;

/**
 * Log entries correlated to one trace, for the trace detail page.
 */
class TraceLogsQuery
{
    /**
     * @return Collection<int, OtelLog>
     */
    public function execute(Project $project, string $traceId, int $limit = 200): Collection
    {
        return $project->logs()
            ->where('trace_id', $traceId)
            ->orderBy('time')
            ->select(['time', 'span_id', 'severity_text', 'severity_number', 'body'])
            // Capped so a pathological trace (a tight logging loop) can't make this page unbounded.
            ->limit($limit)
            ->get();
    }
}
