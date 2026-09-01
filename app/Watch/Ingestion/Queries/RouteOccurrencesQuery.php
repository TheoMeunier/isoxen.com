<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\RouteFingerprint;
use App\Watch\Ingestion\Support\RouteName;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A single route's full detail, addressed by the fingerprint RouteStatsQuery
 * groups the Requests page's Routes table by (see RouteFingerprint): every
 * hit within the window -- so the drawer/detail page can show traffic and
 * status-class volume over time, duration stats, and the most recent
 * individual requests, each annotated with what it triggered downstream
 * (see RouteRequestActivityQuery) -- without a dedicated per-route table,
 * the same one-scan-per-view tradeoff QueryOccurrencesQuery and
 * ExceptionOccurrencesQuery already make.
 */
class RouteOccurrencesQuery
{
    /**
     * How many of the most recent requests to keep the full detail (time,
     * method, status, duration, trace) for -- unbounded would mean holding
     * a busy route's entire request volume in memory just to show "what
     * happened recently", so this keeps a fixed-size, most-recent-first
     * window instead (totals/duration stats above are unaffected: those
     * still scan every row in the window, matching RouteStatsQuery). It
     * also bounds RouteRequestActivityQuery's own scan below to at most
     * this many trace ids.
     */
    private const int MAX_OCCURRENCES = 50;

    public function __construct(
        private readonly RouteRequestActivityQuery $routeRequestActivityQuery,
    ) {}

    /**
     * @return array{
     *     path: string,
     *     fingerprint: string,
     *     methods: string,
     *     route_name: string|null,
     *     action: string|null,
     *     total: int,
     *     success: int,
     *     client_error: int,
     *     server_error: int,
     *     avg_ms: float,
     *     p95_ms: float,
     *     first_seen: string|null,
     *     last_seen: string|null,
     *     timeline: list<array{at: string, total: int, success: int, client_error: int, server_error: int, avg_ms: float|null, p95_ms: float|null}>,
     *     occurrences: list<array{
     *         time: string,
     *         method: string|null,
     *         status_code: int|null,
     *         duration_ms: float,
     *         url: string|null,
     *         client_address: string|null,
     *         request_size: int|null,
     *         response_size: int|null,
     *         peak_memory: int|null,
     *         user: array{id: string|null, name: string|null, email: string|null}|null,
     *         trace_id: string|null,
     *         span_id: string|null,
     *         activity: array{
     *             queries: array{count: int, total_ms: float},
     *             outgoing_requests: array{count: int},
     *             queued_jobs: array{count: int},
     *             cache: array{hits: int, misses: int, writes: int},
     *             mail: array{count: int},
     *             notifications: array{count: int},
     *         },
     *         storage: array{reads: int, writes: int},
     *         hydrated_models: int,
     *     }>,
     * }|null
     */
    public function execute(Project $project, string $fingerprint, int $hours = 24): ?array
    {
        $since = Date::now('UTC')->subHours($hours - 1)->startOfHour();

        /** @var array<string, array{at: string, total: int, success: int, client_error: int, server_error: int, duration_nanos: int, durations: list<int>}> $buckets */
        $buckets = [];
        for ($hour = 0; $hour < $hours; $hour++) {
            $at                                 = $since->copy()->addHours($hour);
            $buckets[$at->format('Y-m-d H:00')] = [
                'at'             => $at->toIso8601String(),
                'total'          => 0,
                'success'        => 0,
                'client_error'   => 0,
                'server_error'   => 0,
                'duration_nanos' => 0,
                'durations'      => [],
            ];
        }

        $path      = null;
        $routeName = null;
        $action    = null;
        $firstSeen = null;
        $lastSeen  = null;

        /** @var array<string, true> $methods */
        $methods = [];

        $success     = 0;
        $clientError = 0;
        $serverError = 0;

        /** @var list<int> $durations */
        $durations = [];

        /** @var list<array{time: Carbon, method: string|null, status_code: int|null, duration_ms: float, url: string|null, client_address: string|null, request_size: int|null, response_size: int|null, peak_memory: int|null, user: array{id: string|null, name: string|null, email: string|null}|null, trace_id: string|null, span_id: string|null, storage: array{reads: int, writes: int}, hydrated_models: int}> $occurrences */
        $occurrences = [];

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->where('time', '>=', $since)
            ->whereNotNull('name')
            ->whereNotNull('duration_nanos')
            ->select('time', 'name', 'duration_nanos', 'attributes', 'trace_id', 'span_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (
                &$path,
                &$routeName,
                &$action,
                &$firstSeen,
                &$lastSeen,
                &$methods,
                &$success,
                &$clientError,
                &$serverError,
                &$durations,
                &$occurrences,
                &$buckets,
                $fingerprint,
            ): void {
                foreach ($rows as $row) {
                    [$method, $rowPath] = RouteName::split((string) $row->name);

                    if ($rowPath === null || RouteFingerprint::make($rowPath) !== $fingerprint) {
                        continue;
                    }

                    $path ??= $rowPath;

                    if ($method !== null) {
                        $methods[$method] = true;
                    }

                    $attributes = json_decode((string) $row->attributes, true) ?? [];

                    $rowRouteName = $attributes['isoxen.route.name'] ?? null;
                    $routeName ??= is_string($rowRouteName) && $rowRouteName !== '' ? $rowRouteName : null;

                    $rowAction = $attributes['code.function.name'] ?? null;
                    $action ??= is_string($rowAction) && $rowAction !== '' ? $rowAction : null;

                    $status = $attributes['http.response.status_code'] ?? null;
                    $status = is_numeric($status) ? (int) $status : null;

                    match (true) {
                        $status === null => null,
                        $status >= 500   => $serverError++,
                        $status >= 400   => $clientError++,
                        default          => $success++,
                    };

                    /** @var Carbon $time */
                    $time       = Date::parse((string) $row->time);
                    $durationNs = (int) $row->duration_nanos;

                    $durations[] = $durationNs;

                    if ($firstSeen === null || $time->lt($firstSeen)) {
                        $firstSeen = $time;
                    }

                    if ($lastSeen === null || $time->gte($lastSeen)) {
                        $lastSeen = $time;
                    }

                    $bucketKey = $time->copy()->setTimezone('UTC')->format('Y-m-d H:00');

                    if (isset($buckets[$bucketKey])) {
                        $buckets[$bucketKey]['total']++;
                        $buckets[$bucketKey]['duration_nanos'] += $durationNs;
                        $buckets[$bucketKey]['durations'][] = $durationNs;

                        match (true) {
                            $status === null => null,
                            $status >= 500   => $buckets[$bucketKey]['server_error']++,
                            $status >= 400   => $buckets[$bucketKey]['client_error']++,
                            default          => $buckets[$bucketKey]['success']++,
                        };
                    }

                    $url           = $attributes['url.full']       ?? null;
                    $clientAddress = $attributes['client.address'] ?? null;

                    $requestSize  = $attributes['http.request.body.size']  ?? null;
                    $responseSize = $attributes['http.response.body.size'] ?? null;
                    $peakMemory   = $attributes['isoxen.request.peak_memory'] ?? null;

                    $storageReads  = $attributes['isoxen.request.storage_reads']  ?? null;
                    $storageWrites = $attributes['isoxen.request.storage_writes'] ?? null;
                    $hydratedModels = $attributes['isoxen.request.hydrated_models'] ?? null;

                    $occurrences[] = [
                        'time'           => $time,
                        'method'         => $method,
                        'status_code'    => $status,
                        'duration_ms'    => round($durationNs / 1_000_000, 2),
                        'url'            => is_string($url)           && $url                     !== '' ? $url : null,
                        'client_address' => is_string($clientAddress) && $clientAddress !== '' ? $clientAddress : null,
                        'request_size'   => is_numeric($requestSize) ? (int) $requestSize : null,
                        'response_size'  => is_numeric($responseSize) ? (int) $responseSize : null,
                        'peak_memory'    => is_numeric($peakMemory) ? (int) $peakMemory : null,
                        'user'           => $this->extractUser($attributes),
                        'trace_id'       => is_string($row->trace_id) ? $row->trace_id : null,
                        'span_id'        => is_string($row->span_id) ? $row->span_id : null,
                        'storage'        => [
                            'reads'  => is_numeric($storageReads) ? (int) $storageReads : 0,
                            'writes' => is_numeric($storageWrites) ? (int) $storageWrites : 0,
                        ],
                        'hydrated_models' => is_numeric($hydratedModels) ? (int) $hydratedModels : 0,
                    ];

                    // The scan visits rows oldest-first, so trimming from the
                    // front here always drops the oldest of what's been kept
                    // -- the array ends up holding only the most recent
                    // MAX_OCCURRENCES requests no matter how many matched
                    // overall.
                    if (count($occurrences) > self::MAX_OCCURRENCES) {
                        array_shift($occurrences);
                    }
                }
            });

        if ($path === null || !$firstSeen instanceof \Illuminate\Support\Carbon || !$lastSeen instanceof \Illuminate\Support\Carbon) {
            return null;
        }

        sort($durations);
        $total      = count($durations);
        $totalNanos = array_sum($durations);

        $traceIds = array_values(array_unique(array_filter(array_column($occurrences, 'trace_id'))));
        $activity = $this->routeRequestActivityQuery->execute($project, $traceIds);
        $noActivity = [
            'queries'           => ['count' => 0, 'total_ms' => 0.0],
            'outgoing_requests' => ['count' => 0],
            'queued_jobs'       => ['count' => 0],
            'cache'             => ['hits' => 0, 'misses' => 0, 'writes' => 0],
            'mail'              => ['count' => 0],
            'notifications'     => ['count' => 0],
        ];

        return [
            'path'         => $path,
            'fingerprint'  => $fingerprint,
            'methods'      => RouteName::formatMethods(array_keys($methods)),
            'route_name'   => $routeName,
            'action'       => $action,
            'total'        => $total,
            'success'      => $success,
            'client_error' => $clientError,
            'server_error' => $serverError,
            'avg_ms'       => $total > 0 ? round($totalNanos / $total / 1_000_000, 1) : 0.0,
            'p95_ms'       => $this->percentile($durations, 0.95),
            'first_seen'   => $firstSeen->toIso8601String(),
            'last_seen'    => $lastSeen->toIso8601String(),
            'timeline'     => array_values(array_map(function (array $bucket): array {
                $bucketDurations = $bucket['durations'];
                sort($bucketDurations);

                return [
                    'at'           => $bucket['at'],
                    'total'        => $bucket['total'],
                    'success'      => $bucket['success'],
                    'client_error' => $bucket['client_error'],
                    'server_error' => $bucket['server_error'],
                    'avg_ms'       => $bucket['total'] > 0 ? round($bucket['duration_nanos'] / $bucket['total'] / 1_000_000, 1) : null,
                    'p95_ms'       => $bucketDurations === [] ? null : $this->percentile($bucketDurations, 0.95),
                ];
            }, $buckets)),
            'occurrences' => array_map(fn (array $occurrence): array => [
                'time'            => $occurrence['time']->toIso8601String(),
                'method'          => $occurrence['method'],
                'status_code'     => $occurrence['status_code'],
                'duration_ms'     => $occurrence['duration_ms'],
                'url'             => $occurrence['url'],
                'client_address'  => $occurrence['client_address'],
                'request_size'    => $occurrence['request_size'],
                'response_size'   => $occurrence['response_size'],
                'peak_memory'     => $occurrence['peak_memory'],
                'user'            => $occurrence['user'],
                'trace_id'        => $occurrence['trace_id'],
                'span_id'         => $occurrence['span_id'],
                'activity'        => $occurrence['trace_id'] !== null
                    ? ($activity[$occurrence['trace_id']] ?? $noActivity)
                    : $noActivity,
                'storage'         => $occurrence['storage'],
                'hydrated_models' => $occurrence['hydrated_models'],
            ], array_reverse($occurrences)),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{id: string|null, name: string|null, email: string|null}|null
     */
    private function extractUser(array $attributes): ?array
    {
        // `user.id` is what UserContextResolver's default collector sets
        // (see collectUserContext); `enduser.id` is the login/logout
        // sensor's own attribute (see UserInstrumentation). name/email
        // only ever appear if the app registered a custom resolver via
        // OpenTelemetry::user() -- absent otherwise, same as Nightwatch's
        // own default.
        $id = $attributes['user.id'] ?? $attributes['enduser.id'] ?? null;
        $id = is_string($id) || is_numeric($id) ? (string) $id : null;

        $name = $attributes['user.name'] ?? null;
        $name = is_string($name) && $name !== '' ? $name : null;

        $email = $attributes['user.email'] ?? null;
        $email = is_string($email) && $email !== '' ? $email : null;

        if ($id === null && $name === null && $email === null) {
            return null;
        }

        return ['id' => $id, 'name' => $name, 'email' => $email];
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
