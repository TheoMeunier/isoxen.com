<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Data\QueryStat;
use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\QueryFingerprint;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;

class QueryStatsQuery
{
    public function execute(Project $project, int $hours = 24): Collection
    {
        $since = \Illuminate\Support\Facades\Date::now()->subHours($hours);

        $queries = [];

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'query')
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->select('duration_nanos', 'attributes')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$queries): void {
                foreach ($rows as $row) {
                    $attributes = json_decode((string) $row->attributes, true) ?? [];

                    $text = $attributes['db.query.text'] ?? null;
                    $text = is_string($text) && $text !== '' ? $text : QueryFingerprint::UNKNOWN_QUERY;

                    $system = $attributes['db.system.name'] ?? null;
                    $system = is_string($system) && $system !== '' ? $system : null;

                    $queries[$text] ??= [
                        'system'    => $system,
                        'durations' => [],
                    ];

                    $queries[$text]['system'] ??= $system;

                    $queries[$text]['durations'][] = (int) $row->duration_nanos;
                }
            });

        return collect($queries)
            ->map(function (array $query, string $text): QueryStat {
                $durations = $query['durations'];
                sort($durations);
                $calls = count($durations);
                $totalNanos = array_sum($durations);

                return new QueryStat(
                    query: $text,
                    fingerprint: QueryFingerprint::make($text),
                    system: $query['system'],
                    calls: $calls,
                    total_ms: round($totalNanos / 1_000_000, 1),
                    avg_ms: $calls > 0 ? round($totalNanos / $calls / 1_000_000, 1) : 0.0,
                    p95_ms: $this->percentile($durations, 0.95),
                );
            })
            ->sortByDesc('calls')
            ->values();
    }

    private function percentile(array $sortedDurationsNanos, float $fraction): float
    {
        $total = count($sortedDurationsNanos);

        if ($total === 0) {
            return 0.0;
        }

        $offset = min((int) floor($total * $fraction), $total - 1);

        return round($sortedDurationsNanos[$offset] / 1_000_000, 1);
    }
}
