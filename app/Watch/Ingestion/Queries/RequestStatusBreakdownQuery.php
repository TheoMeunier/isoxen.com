<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Buckets the Requests category's spans by HTTP status class, mirroring
 * Laravel Nightwatch's "1/2/3XX, 4XX, 5XX" breakdown on its Requests tab.
 *
 * `http.response.status_code` lives inside the span's `attributes` JSON
 * blob rather than its own column (see OtlpSpansParser -- only
 * `isoxen.type` gets promoted to a real column), so this reads and decodes
 * every matching row rather than grouping in SQL. That's fine at the size
 * of a 24-hour window; it stops being fine if this window is ever widened
 * without adding a real column for it.
 */
class RequestStatusBreakdownQuery
{
    /**
     * @return array{success: int, client_error: int, server_error: int}
     */
    public function execute(Project $project, int $hours = 24): array
    {
        $since = Carbon::now()->subHours($hours);

        $counts = ['success' => 0, 'client_error' => 0, 'server_error' => 0];

        DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('time', '>=', $since)
            ->whereNotNull('attributes')
            ->select('attributes')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$counts) {
                foreach ($rows as $row) {
                    $status = json_decode((string) $row->attributes, true)['http.response.status_code'] ?? null;

                    if (! is_int($status) && ! is_numeric($status)) {
                        continue;
                    }

                    $status = (int) $status;

                    match (true) {
                        $status >= 500 => $counts['server_error']++,
                        $status >= 400 => $counts['client_error']++,
                        default        => $counts['success']++,
                    };
                }
            });

        return $counts;
    }

    /**
     * The same breakdown as {@see self::execute()}, per hour instead of
     * summed over the whole window -- what the volume chart's stacked bars
     * are made of.
     *
     * A second full scan of the window rather than sharing one pass with
     * execute(): simpler and lower-risk than threading two accumulators
     * through one chunked callback, at the cost of decoding every row's
     * `attributes` blob twice per page load. Worth revisiting if this page
     * ever needs to feel snappier under real load.
     *
     * @return array<int, array{at: string, success: int, client_error: int, server_error: int}>
     */
    public function executeTimeline(Project $project, int $hours = 24): array
    {
        $since = Carbon::now('UTC')->subHours($hours - 1)->startOfHour();

        $buckets = [];
        for ($hour = 0; $hour < $hours; $hour++) {
            $at                                 = $since->copy()->addHours($hour);
            $buckets[$at->format('Y-m-d H:00')] = [
                'at'           => $at->toIso8601String(),
                'success'      => 0,
                'client_error' => 0,
                'server_error' => 0,
            ];
        }

        DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('time', '>=', $since)
            ->whereNotNull('attributes')
            ->select('attributes', 'time')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$buckets) {
                foreach ($rows as $row) {
                    $status = json_decode((string) $row->attributes, true)['http.response.status_code'] ?? null;

                    if (! is_int($status) && ! is_numeric($status)) {
                        continue;
                    }

                    // The row's own timestamp, not the loop variable above:
                    // this key has to land in the same bucket the SQL-side
                    // queries would put it in, which means truncating to
                    // the hour in UTC exactly like BucketsByHour does.
                    $key = Carbon::parse($row->time, 'UTC')->format('Y-m-d H:00');

                    if (! isset($buckets[$key])) {
                        continue;
                    }

                    $status = (int) $status;

                    match (true) {
                        $status >= 500 => $buckets[$key]['server_error']++,
                        $status >= 400 => $buckets[$key]['client_error']++,
                        default        => $buckets[$key]['success']++,
                    };
                }
            });

        return array_values($buckets);
    }
}
