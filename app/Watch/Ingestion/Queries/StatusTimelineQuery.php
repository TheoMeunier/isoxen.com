<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\BucketsByHour;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The generic Ok/Error split (OTEL status_code), per hour, for every span
 * category that doesn't get a bespoke breakdown of its own (Requests gets
 * HTTP status classes, Logs gets severity -- see their own *BreakdownQuery
 * classes). This is what the volume chart's stacked bars are made of for
 * Jobs, Commands, Queries, and everything else.
 */
class StatusTimelineQuery
{
    use BucketsByHour;

    /**
     * @return array<int, array{at: string, success: int, error: int}>
     */
    public function execute(Project $project, ?string $type, int $hours = 24): array
    {
        $since  = Carbon::now('UTC')->subHours($hours - 1)->startOfHour();
        $bucket = $this->bucketExpression();

        $rows = DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->where('time', '>=', $since)
            ->groupByRaw("{$bucket}, status_code")
            ->selectRaw("{$bucket} as bucket, status_code, count(*) as aggregate")
            ->get();

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row->bucket][(int) $row->status_code] = (int) $row->aggregate;
        }

        $series = [];

        for ($hour = 0; $hour < $hours; $hour++) {
            $at       = $since->copy()->addHours($hour);
            $statuses = $keyed[$at->format('Y-m-d H:00')] ?? [];
            // OTEL status code 2 is ERROR; everything else (Unset, Ok)
            // counts as the healthy segment, matching CategorySummaryQuery.
            $error = $statuses[2] ?? 0;

            $series[] = [
                'at'      => $at->toIso8601String(),
                'success' => array_sum($statuses) - $error,
                'error'   => $error,
            ];
        }

        return $series;
    }
}
