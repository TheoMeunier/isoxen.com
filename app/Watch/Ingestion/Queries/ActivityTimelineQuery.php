<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelRecord;
use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Queries\Concerns\BucketsByHour;
use App\Watch\Projects\Models\Project;

class ActivityTimelineQuery
{
    use BucketsByHour;

    /**
     * @param  class-string<OtelRecord>  $model
     * @return array<int, array{at: string, count: int}>
     */
    public function execute(Project $project, string $model, ?string $type = null, int $hours = 24): array
    {
        $since  = \Illuminate\Support\Facades\Date::now('UTC')->subHours($hours - 1)->startOfHour();
        $bucket = $this->bucketExpression();

        $counts = $model::query()->toBase()
            ->where('project_id', $project->id)
            ->when($type !== null && $model === OtelSpan::class, fn ($query) => $query->where('type', $type))
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
