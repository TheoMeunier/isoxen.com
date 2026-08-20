<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\ReturnsIsoTimestamps;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every span that belongs to one trace, for the waterfall on a trace's
 * detail page.
 *
 * Unlike RecentSpansQuery -- one category, paginated, most recent first --
 * this reads the whole trace regardless of `type` and orders it
 * chronologically: a waterfall needs every child span (queries, cache, jobs
 * dispatched, ...) under the request or command that triggered them, not
 * just the entries of one category. A trace is small enough in practice
 * (one request's worth of spans) that pagination isn't worth the
 * complexity it would add to the waterfall's tree layout.
 */
class TraceSpansQuery
{
    use ReturnsIsoTimestamps;

    /**
     * @return Collection<int, object>
     */
    public function execute(Project $project, string $traceId): Collection
    {
        return DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('trace_id', $traceId)
            ->orderBy('time')
            ->select([
                'span_id', 'parent_span_id', 'name', 'type', 'kind',
                'time', 'end_time', 'duration_nanos',
                'status_code', 'status_message', 'attributes',
            ])
            ->get()
            ->map(function (object $row): object {
                // Stored as a JSON string (see OtlpSpansParser), decoded here
                // so the frontend gets a plain object rather than a string it
                // would have to parse itself.
                $row->attributes = $row->attributes === null
                    ? null
                    : json_decode((string) $row->attributes, true);

                return $this->toIso($row, ['time', 'end_time']);
            });
    }
}
