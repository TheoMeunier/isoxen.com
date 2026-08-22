<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\ReturnsIsoTimestamps;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                $row->attributes = $row->attributes === null
                    ? null
                    : json_decode((string)$row->attributes, true);

                return $this->toIso($row, ['time', 'end_time']);
            });
    }
}
