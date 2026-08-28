<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\QueryFingerprint;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;

/**
 * The Queries page's main table: every SQL statement run in the window,
 * grouped by its exact text -- the same "collapse the noisy stream into
 * one row per shape of thing that happened" idea as RouteStatsQuery does
 * for routes, since a list of thousands of individual query spans is far
 * less useful here than "which statements run often or run slow".
 */
class QueryStatsQuery
{
    /**
     * @return Collection<int, object{query: string, fingerprint: string, system: string|null, calls: int, total_ms: float, avg_ms: float, p95_ms: float}>
     */
    public function execute(Project $project, int $hours = 24): Collection
    {
        $since = \Illuminate\Support\Facades\Date::now()->subHours($hours);

        /** @var array<string, array{system: string|null, durations: list<int>}> $queries */
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

                    // The same statement text should only ever come from one
                    // connection type in practice, but a group's first-seen
                    // value wins over a later, possibly missing one rather
                    // than flickering between rows.
                    $queries[$text]['system'] ??= $system;

                    $queries[$text]['durations'][] = (int) $row->duration_nanos;
                }
            });

        return collect($queries)
            ->map(function (array $query, string $text): object {
                $durations = $query['durations'];
                sort($durations);
                $calls = count($durations);
                $totalNanos = array_sum($durations);

                return (object) [
                    'query'       => $text,
                    'fingerprint' => QueryFingerprint::make($text),
                    'system'      => $query['system'],
                    'calls'       => $calls,
                    'total_ms'    => round($totalNanos / 1_000_000, 1),
                    'avg_ms'      => $calls > 0 ? round($totalNanos / $calls / 1_000_000, 1) : 0.0,
                    'p95_ms'      => $this->percentile($durations, 0.95),
                ];
            })
            ->sortByDesc('calls')
            ->values();
    }

    /**
     * @param  list<int>  $sortedDurationsNanos
     */
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
