<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\ReturnsIsoTimestamps;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Log entries correlated to one trace, for the trace detail page.
 *
 * A log carries a trace/span id when it was written while that span was
 * active (see LogInstrumentation in the client), which is what lets a
 * request's own log lines show up next to the span that produced them.
 */
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
            // A trace is bounded by one request/command's lifetime, but a
            // pathological one (a tight logging loop) shouldn't be able to
            // make this page unbounded -- the newest lines within the trace
            // matter most, so a cap here rather than no limit at all.
            ->limit($limit)
            ->get()
            ->map(fn (object $row): object => $this->toIso($row));
    }
}
