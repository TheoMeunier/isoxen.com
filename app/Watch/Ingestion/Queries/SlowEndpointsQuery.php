<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Groups a project's request spans by endpoint (span name) to answer "where
 * is this app slow", rather than "what happened most recently" -- the
 * question the plain Requests table can't answer without reading every row.
 *
 * Scoped to the same window as CategorySummaryQuery for the same reason:
 * comparing an endpoint's recent p95 against an all-time one would be
 * misleading.
 */
class SlowEndpointsQuery
{
    /**
     * @return Collection<int, object{name: string, total: int, errors: int, avg_ms: float, p50_ms: float, p95_ms: float, p99_ms: float}>
     */
    public function execute(Project $project, int $hours = 24, int $limit = 20): Collection
    {
        $since = Carbon::now()->subHours($hours);

        // First pass: cheap, portable aggregates (COUNT/AVG/SUM run
        // identically on Postgres and SQLite -- the latter is what the test
        // suite uses, and `percentile_cont` doesn't exist there). Capped at
        // $limit busiest endpoints so the second pass below stays bounded.
        $endpoints = DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->whereNotNull('name')
            ->selectRaw('name, COUNT(*) as total, AVG(duration_nanos) as avg_nanos, SUM(CASE WHEN status_code = 2 THEN 1 ELSE 0 END) as errors')
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        // Second pass: for each endpoint, the same order-and-offset trick
        // CategorySummaryQuery uses for its p95 -- no percentile function
        // required, at the cost of one query per percentile per endpoint.
        // That's up to 3 * $limit extra queries, which is fine for the
        // handful of endpoints a project has, but don't raise $limit much
        // without revisiting this.
        return $endpoints
            ->map(function (object $endpoint) use ($project, $since): object {
                $total = (int) $endpoint->total;

                return (object) [
                    'name'   => $endpoint->name,
                    'total'  => $total,
                    'errors' => (int) $endpoint->errors,
                    'avg_ms' => round((float) $endpoint->avg_nanos / 1_000_000, 1),
                    'p50_ms' => $this->percentileMs($project, $endpoint->name, $since, $total, 0.50),
                    'p95_ms' => $this->percentileMs($project, $endpoint->name, $since, $total, 0.95),
                    'p99_ms' => $this->percentileMs($project, $endpoint->name, $since, $total, 0.99),
                ];
            })
            ->sortByDesc('p95_ms')
            ->values();
    }

    private function percentileMs(Project $project, string $name, Carbon $since, int $total, float $fraction): float
    {
        $offset = min((int) floor($total * $fraction), $total - 1);

        $nanos = DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('name', $name)
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->orderBy('duration_nanos')
            ->offset($offset)
            ->limit(1)
            ->value('duration_nanos');

        return $nanos === null ? 0.0 : round((int) $nanos / 1_000_000, 1);
    }
}
