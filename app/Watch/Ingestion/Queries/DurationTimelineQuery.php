<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Queries\Concerns\BucketsByHour;
use App\Watch\Projects\Models\Project;

class DurationTimelineQuery
{
    use BucketsByHour;

    /**
     * @return array<int, array{at: string, avg_ms: float|null, p95_ms: float|null}>
     */
    public function execute(Project $project, ?string $type = null, int $hours = 24): array
    {
        $since  = \Illuminate\Support\Facades\Date::now('UTC')->subHours($hours - 1)->startOfHour();
        $bucket = $this->bucketExpression();

        $averages = OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->groupByRaw($bucket)
            ->selectRaw("{$bucket} as bucket, avg(duration_nanos) as aggregate")
            ->pluck('aggregate', 'bucket');

        /** @var array<string, list<int>> $durationsByBucket */
        $durationsByBucket = [];

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->selectRaw("{$bucket} as bucket, duration_nanos")
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$durationsByBucket): void {
                foreach ($rows as $row) {
                    $durationsByBucket[$row->bucket][] = (int) $row->duration_nanos;
                }
            });

        $series = [];

        for ($hour = 0; $hour < $hours; $hour++) {
            $at    = $since->copy()->addHours($hour);
            $key   = $at->format('Y-m-d H:00');
            $nanos = $averages[$key] ?? null;

            $durations = $durationsByBucket[$key] ?? [];
            sort($durations);

            $series[] = [
                'at'     => $at->toIso8601String(),
                'avg_ms' => $nanos === null ? null : round((float) $nanos / 1_000_000, 1),
                'p95_ms' => $this->percentile($durations),
            ];
        }

        return $series;
    }

    /**
     * @param  list<int>  $sortedDurationsNanos
     */
    private function percentile(array $sortedDurationsNanos): ?float
    {
        $total = count($sortedDurationsNanos);

        if ($total === 0) {
            return null;
        }

        $offset = min((int) floor($total * 0.95), $total - 1);

        return round($sortedDurationsNanos[$offset] / 1_000_000, 1);
    }
}
