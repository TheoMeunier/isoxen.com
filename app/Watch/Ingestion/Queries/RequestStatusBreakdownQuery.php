<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RequestStatusBreakdownQuery
{
    /**
     * @return array{success: int, client_error: int, server_error: int}
     */
    public function execute(Project $project, int $hours = 24): array
    {
        $since = \Illuminate\Support\Facades\Date::now()->subHours($hours);

        $counts = ['success' => 0, 'client_error' => 0, 'server_error' => 0];

        DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('time', '>=', $since)
            ->whereNotNull('attributes')
            ->select('attributes')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$counts): void {
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
     * The same breakdown as {@see self::execute()}, per hour, in a second scan rather than one shared pass.
     *
     * @return array<int, array{at: string, success: int, client_error: int, server_error: int}>
     */
    public function executeTimeline(Project $project, int $hours = 24): array
    {
        $since = \Illuminate\Support\Facades\Date::now('UTC')->subHours($hours - 1)->startOfHour();

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
            ->chunk(500, function ($rows) use (&$buckets): void {
                foreach ($rows as $row) {
                    $status = json_decode((string) $row->attributes, true)['http.response.status_code'] ?? null;

                    if (! is_int($status) && ! is_numeric($status)) {
                        continue;
                    }

                    $key = \Illuminate\Support\Facades\Date::parse($row->time, 'UTC')->format('Y-m-d H:00');

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
