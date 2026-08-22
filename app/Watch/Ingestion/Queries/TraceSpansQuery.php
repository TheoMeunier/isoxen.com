<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;

/**
 * Every span of one trace, chronological and unpaginated, for the waterfall on the trace detail page.
 */
class TraceSpansQuery
{
    /**
     * @return Collection<int, OtelSpan>
     */
    public function execute(Project $project, string $traceId): Collection
    {
        return $project->spans()
            ->where('trace_id', $traceId)
            ->orderBy('time')
            ->select([
                'span_id', 'parent_span_id', 'name', 'type', 'kind',
                'time', 'end_time', 'duration_nanos',
                'status_code', 'status_message', 'attributes',
            ])
            ->get();
    }
}
