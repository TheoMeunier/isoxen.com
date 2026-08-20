<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\BucketsByHour;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Counts entries per hour for the chart on a project's page.
 *
 * Empty hours are filled in rather than skipped: a gap in the data should
 * read as a quiet hour, not compress the timeline and imply activity that
 * never happened.
 *
 * Every hour here is a UTC hour, on both sides — the SQL bucket and the PHP
 * key that looks it up have to agree, and leaving either to a session
 * default is how they quietly stop agreeing. What the reader sees is
 * converted to their own timezone in the browser, from the ISO timestamp
 * each bucket carries.
 */
class ActivityTimelineQuery
{
    use BucketsByHour;

    /**
     * @return array<int, array{at: string, count: int}>
     */
    public function execute(Project $project, string $table, ?string $type = null, int $hours = 24): array
    {
        $since  = Carbon::now('UTC')->subHours($hours - 1)->startOfHour();
        $bucket = $this->bucketExpression();

        $counts = DB::table($table)
            ->where('project_id', $project->id)
            // Only `otel_spans` carries the category type. `otel_metrics`
            // also has a `type` column, but it means something else
            // entirely (gauge/sum/histogram), so it must never be filtered
            // with a category here.
            ->when($type !== null && $table === 'otel_spans', fn ($query) => $query->where('type', $type))
            ->where('time', '>=', $since)
            ->groupByRaw($bucket)
            ->selectRaw("{$bucket} as bucket, count(*) as aggregate")
            ->pluck('aggregate', 'bucket');

        $series = [];

        for ($hour = 0; $hour < $hours; $hour++) {
            $at = $since->copy()->addHours($hour);

            $series[] = [
                'at'    => $at->toIso8601String(),
                'count' => (int) ($counts[$at->format('Y-m-d H:00')] ?? 0),
            ];
        }

        return $series;
    }
}
