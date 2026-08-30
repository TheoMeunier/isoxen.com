<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\RouteFingerprint;
use App\Watch\Ingestion\Support\RouteName;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Collection;

/**
 * The Requests page's main table: every route hit in the window, grouped by
 * path rather than by method+path -- a route registered for GET also
 * answers HEAD, and grouping the methods actually seen for a path (e.g.
 * "GET|HEAD") reads the same way Laravel's own route:list does, instead of
 * splitting one route into several rows.
 */
class RouteStatsQuery
{
    /**
     * @return Collection<int, object{methods: string, path: string, fingerprint: string, total: int, success: int, client_error: int, server_error: int, avg_ms: float, p95_ms: float}>
     */
    public function execute(Project $project, int $hours = 24): Collection
    {
        $since = \Illuminate\Support\Facades\Date::now()->subHours($hours);

        /** @var array<string, array{methods: array<string, true>, durations: list<int>, success: int, client_error: int, server_error: int}> $routes */
        $routes = [];

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('time', '>=', $since)
            ->whereNotNull('name')
            ->whereNotNull('duration_nanos')
            ->select('name', 'duration_nanos', 'attributes')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$routes): void {
                foreach ($rows as $row) {
                    [$method, $path] = RouteName::split((string) $row->name);

                    if ($path === null) {
                        continue;
                    }

                    $routes[$path] ??= [
                        'methods'      => [],
                        'durations'    => [],
                        'success'      => 0,
                        'client_error' => 0,
                        'server_error' => 0,
                    ];

                    if ($method !== null) {
                        $routes[$path]['methods'][$method] = true;
                    }

                    $routes[$path]['durations'][] = (int) $row->duration_nanos;

                    $status = json_decode((string) $row->attributes, true)['http.response.status_code'] ?? null;
                    $status = is_numeric($status) ? (int) $status : null;

                    match (true) {
                        $status === null => null,
                        $status >= 500   => $routes[$path]['server_error']++,
                        $status >= 400   => $routes[$path]['client_error']++,
                        default          => $routes[$path]['success']++,
                    };
                }
            });

        return collect($routes)
            ->map(function (array $route, string $path): object {
                $durations = $route['durations'];
                sort($durations);
                $total = count($durations);

                return (object) [
                    'methods'      => RouteName::formatMethods(array_keys($route['methods'])),
                    'path'         => $path,
                    'fingerprint'  => RouteFingerprint::make($path),
                    'total'        => $total,
                    'success'      => $route['success'],
                    'client_error' => $route['client_error'],
                    'server_error' => $route['server_error'],
                    'avg_ms'       => $total > 0 ? round(array_sum($durations) / $total / 1_000_000, 1) : 0.0,
                    'p95_ms'       => $this->percentile($durations, 0.95),
                ];
            })
            ->sortByDesc('total')
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
