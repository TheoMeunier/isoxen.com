<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\BucketsByHour;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Average duration per hour, for the Duration panel's chart alongside
 * ActivityTimelineQuery's volume chart.
 *
 * Only `otel_spans` carries a `duration_nanos` column -- logs and metrics
 * don't -- so this is only ever called for span-backed categories. An empty
 * hour gets `null`, not `0`: no requests happened, not "requests happened
 * and took 0ms", the same distinction ActivityTimelineQuery's caller has to
 * make for volume but doesn't, because a quiet hour genuinely did see zero
 * events.
 */
class DurationTimelineQuery
{
    use BucketsByHour;

    /**
     * @return array<int, array{at: string, avg_ms: float|null}>
     */
    public function execute(Project $project, ?string $type = null, int $hours = 24): array
    {
        $since  = Carbon::now('UTC')->subHours($hours - 1)->startOfHour();
        $bucket = $this->bucketExpression();

        $averages = DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->groupByRaw($bucket)
            ->selectRaw("{$bucket} as bucket, avg(duration_nanos) as aggregate")
            ->pluck('aggregate', 'bucket');

        $series = [];

        for ($hour = 0; $hour < $hours; $hour++) {
            $at    = $since->copy()->addHours($hour);
            $key   = $at->format('Y-m-d H:00');
            $nanos = $averages[$key] ?? null;

            $series[] = [
                'at'     => $at->toIso8601String(),
                'avg_ms' => $nanos === null ? null : round((float) $nanos / 1_000_000, 1),
            ];
        }

        return $series;
    }
}
