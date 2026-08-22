<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\BucketsByHour;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

class StatusTimelineQuery
{
    use BucketsByHour;

    /**
     * @return array<int, array{at: string, success: int, error: int}>
     */
    public function execute(Project $project, ?string $type, int $hours = 24): array
    {
        $since  = \Illuminate\Support\Facades\Date::now('UTC')->subHours($hours - 1)->startOfHour();
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
            $error    = $statuses[2]                      ?? 0;

            $series[] = [
                'at'      => $at->toIso8601String(),
                'success' => array_sum($statuses) - $error,
                'error'   => $error,
            ];
        }

        return $series;
    }
}
