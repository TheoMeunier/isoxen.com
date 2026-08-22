<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\BucketsByHour;
use App\Watch\Projects\Models\Project;
use Illuminate\Database\Query\Builder;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class LogSeverityBreakdownQuery
{
    use BucketsByHour;

    /**
     * @return array{info: int, warning: int, error: int}
     */
    public function execute(Project $project, int $hours = 24): array
    {
        $since = \Illuminate\Support\Facades\Date::now()->subHours($hours);

        return [
            'info'    => $this->base($project, $since)->where(fn ($q) => $q->whereNull('severity_number')->orWhere('severity_number', '<', 13))->count(),
            'warning' => $this->base($project, $since)->whereBetween('severity_number', [13, 16])->count(),
            'error'   => $this->base($project, $since)->where('severity_number', '>=', 17)->count(),
        ];
    }

    private function base(Project $project, DateTimeInterface $since): Builder
    {
        return DB::table('otel_logs')
            ->where('project_id', $project->id)
            ->where('time', '>=', $since);
    }

    /**
     * The same severity split as {@see self::execute()}, per hour, grouped in SQL since severity is a real column.
     *
     * @return array<int, array{at: string, info: int, warning: int, error: int}>
     */
    public function executeTimeline(Project $project, int $hours = 24): array
    {
        $since    = \Illuminate\Support\Facades\Date::now('UTC')->subHours($hours - 1)->startOfHour();
        $bucket   = $this->bucketExpression();
        $severity = "case when severity_number >= 17 then 'error' when severity_number >= 13 then 'warning' else 'info' end";

        $rows = DB::table('otel_logs')
            ->where('project_id', $project->id)
            ->where('time', '>=', $since)
            ->groupByRaw("{$bucket}, {$severity}")
            ->selectRaw("{$bucket} as bucket, {$severity} as severity, count(*) as aggregate")
            ->get();

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row->bucket][$row->severity] = (int) $row->aggregate;
        }

        $series = [];

        for ($hour = 0; $hour < $hours; $hour++) {
            $at           = $since->copy()->addHours($hour);
            $bucketCounts = $keyed[$at->format('Y-m-d H:00')] ?? [];

            $series[] = [
                'at'      => $at->toIso8601String(),
                'info'    => $bucketCounts['info']    ?? 0,
                'warning' => $bucketCounts['warning'] ?? 0,
                'error'   => $bucketCounts['error']   ?? 0,
            ];
        }

        return $series;
    }
}
