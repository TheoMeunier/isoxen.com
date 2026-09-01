<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\QueryFingerprint;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A single SQL statement's full detail, addressed by the fingerprint
 * QueryStatsQuery groups the Queries table by (see QueryFingerprint): every
 * call within the window -- so the drawer/detail page can show the calls
 * volume over time, duration stats, connection details, the PHP class that
 * issued each recent call, and which request/job/command triggered it --
 * without a dedicated per-query table, the same one-scan-per-view tradeoff
 * RouteStatsQuery and ExceptionOccurrencesQuery already make.
 */
class QueryOccurrencesQuery
{
    /**
     * How many of the most recent calls to keep the full detail (time,
     * duration, caller, trace) for -- unbounded would mean holding a busy
     * statement's entire call volume in memory just to show "what called
     * this", so this keeps a fixed-size, most-recent-first window instead
     * (calls/duration totals above are unaffected: those still scan every
     * row in the window, matching QueryStatsQuery).
     */
    private const int MAX_OCCURRENCES = 50;

    /**
     * @return array{
     *     query: string,
     *     fingerprint: string,
     *     system: string|null,
     *     namespace: string|null,
     *     host: string|null,
     *     port: int|null,
     *     calls: int,
     *     total_ms: float,
     *     avg_ms: float,
     *     p95_ms: float,
     *     first_seen: string|null,
     *     last_seen: string|null,
     *     timeline: list<array{at: string, calls: int, avg_ms: float|null, p95_ms: float|null}>,
     *     occurrences: list<array{time: string, duration_ms: float, trace_id: string|null, span_id: string|null, caller: string|null, caller_file: string|null, caller_line: int|null, called_from: string|null, called_from_type: string|null}>,
     * }|null
     */
    public function execute(Project $project, string $fingerprint, int $hours = 24): ?array
    {
        $since = Date::now('UTC')->subHours($hours - 1)->startOfHour();

        /** @var array<string, array{at: string, calls: int, duration_nanos: int, durations: list<int>}> $buckets */
        $buckets = [];
        for ($hour = 0; $hour < $hours; $hour++) {
            $at                                 = $since->copy()->addHours($hour);
            $buckets[$at->format('Y-m-d H:00')] = [
                'at'             => $at->toIso8601String(),
                'calls'          => 0,
                'duration_nanos' => 0,
                'durations'      => [],
            ];
        }

        $text      = null;
        $system    = null;
        $namespace = null;
        $host      = null;
        $port      = null;
        $firstSeen = null;
        $lastSeen  = null;

        /** @var list<int> $durations */
        $durations = [];

        /** @var list<array{time: Carbon, duration_ms: float, trace_id: string|null, span_id: string|null, caller: string|null, caller_file: string|null, caller_line: int|null}> $occurrences */
        $occurrences = [];

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'query')
            ->where('time', '>=', $since)
            ->whereNotNull('duration_nanos')
            ->select('time', 'duration_nanos', 'attributes', 'trace_id', 'span_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (
                &$text,
                &$system,
                &$namespace,
                &$host,
                &$port,
                &$firstSeen,
                &$lastSeen,
                &$durations,
                &$occurrences,
                &$buckets,
                $fingerprint,
            ): void {
                foreach ($rows as $row) {
                    $attributes = json_decode((string) $row->attributes, true) ?? [];

                    $rowText = $attributes['db.query.text'] ?? null;
                    $rowText = is_string($rowText) && $rowText !== '' ? $rowText : QueryFingerprint::UNKNOWN_QUERY;

                    if (QueryFingerprint::make($rowText) !== $fingerprint) {
                        continue;
                    }

                    $text ??= $rowText;

                    $rowSystem = $attributes['db.system.name'] ?? null;
                    $system ??= is_string($rowSystem) && $rowSystem !== '' ? $rowSystem : null;

                    $rowNamespace = $attributes['db.namespace'] ?? null;
                    $namespace ??= is_string($rowNamespace) && $rowNamespace !== '' ? $rowNamespace : null;

                    $rowHost = $attributes['server.address'] ?? null;
                    $host ??= is_string($rowHost) && $rowHost !== '' ? $rowHost : null;

                    $rowPort = $attributes['server.port'] ?? null;
                    $port ??= is_numeric($rowPort) ? (int) $rowPort : null;

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
                        $buckets[$bucketKey]['calls']++;
                        $buckets[$bucketKey]['duration_nanos'] += $durationNs;
                        $buckets[$bucketKey]['durations'][] = $durationNs;
                    }

                    // The PHP class/method that issued this call (see
                    // QueryInstrumentation::callerAttributes -- the client
                    // walks the call stack past vendor/ to find it), not the
                    // trace's root span: that's resolved separately below,
                    // per trace rather than per call.
                    $caller     = $attributes['code.function.name'] ?? null;
                    $callerFile = $attributes['code.file.path']     ?? null;
                    $callerLine = $attributes['code.line.number']   ?? null;

                    $occurrences[] = [
                        'time'        => $time,
                        'duration_ms' => round($durationNs / 1_000_000, 2),
                        'trace_id'    => is_string($row->trace_id) ? $row->trace_id : null,
                        'span_id'     => is_string($row->span_id) ? $row->span_id : null,
                        'caller'      => is_string($caller)     && $caller         !== '' ? $caller : null,
                        'caller_file' => is_string($callerFile) && $callerFile !== '' ? $callerFile : null,
                        'caller_line' => is_numeric($callerLine) ? (int) $callerLine : null,
                    ];

                    // The scan visits rows oldest-first, so trimming from the
                    // front here always drops the oldest of what's been kept
                    // -- the array ends up holding only the most recent
                    // MAX_OCCURRENCES calls no matter how many matched overall.
                    if (count($occurrences) > self::MAX_OCCURRENCES) {
                        array_shift($occurrences);
                    }
                }
            });

        if ($text === null || !$firstSeen instanceof \Illuminate\Support\Carbon || !$lastSeen instanceof \Illuminate\Support\Carbon) {
            return null;
        }

        sort($durations);
        $calls      = count($durations);
        $totalNanos = array_sum($durations);

        $recentFirst = array_reverse($occurrences);
        $triggers    = $this->resolveTriggers($project, $recentFirst);

        return [
            'query'       => $text,
            'fingerprint' => $fingerprint,
            'system'      => $system,
            'namespace'   => $namespace,
            'host'        => $host,
            'port'        => $port,
            'calls'       => $calls,
            'total_ms'    => round($totalNanos / 1_000_000, 1),
            'avg_ms'      => $calls > 0 ? round($totalNanos / $calls / 1_000_000, 1) : 0.0,
            'p95_ms'      => $this->percentile($durations, 0.95),
            'first_seen'  => $firstSeen->toIso8601String(),
            'last_seen'   => $lastSeen->toIso8601String(),
            'timeline'    => array_values(array_map(function (array $bucket): array {
                $bucketDurations = $bucket['durations'];
                sort($bucketDurations);

                return [
                    'at'     => $bucket['at'],
                    'calls'  => $bucket['calls'],
                    'avg_ms' => $bucket['calls'] > 0 ? round($bucket['duration_nanos'] / $bucket['calls'] / 1_000_000, 1) : null,
                    'p95_ms' => $bucketDurations === [] ? null : $this->percentile($bucketDurations, 0.95),
                ];
            }, $buckets)),
            'occurrences' => array_map(fn (array $occurrence): array => [
                'time'             => $occurrence['time']->toIso8601String(),
                'duration_ms'      => $occurrence['duration_ms'],
                'trace_id'         => $occurrence['trace_id'],
                'span_id'          => $occurrence['span_id'],
                'caller'           => $occurrence['caller'],
                'caller_file'      => $occurrence['caller_file'],
                'caller_line'      => $occurrence['caller_line'],
                'called_from'      => $occurrence['trace_id'] !== null ? ($triggers[$occurrence['trace_id']]['name'] ?? null) : null,
                'called_from_type' => $occurrence['trace_id'] !== null ? ($triggers[$occurrence['trace_id']]['type'] ?? null) : null,
            ], $recentFirst),
        ];
    }

    /**
     * The span that started each occurrence's trace -- an HTTP request, a
     * job, a command, ... -- shown alongside the PHP caller above so the
     * drawer can say both *what code* ran the query and *what triggered*
     * that code. One query keyed by trace_id rather than a lookup per
     * occurrence, since the occurrences list is already capped.
     *
     * @param  list<array{time: Carbon, duration_ms: float, trace_id: string|null, span_id: string|null, caller: string|null, caller_file: string|null, caller_line: int|null}>  $occurrences
     * @return array<string, array{name: string|null, type: string|null}>
     */
    private function resolveTriggers(Project $project, array $occurrences): array
    {
        $traceIds = array_values(array_unique(array_filter(array_column($occurrences, 'trace_id'))));

        if ($traceIds === []) {
            return [];
        }

        return OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->whereIn('trace_id', $traceIds)
            ->whereNull('parent_span_id')
            ->select('trace_id', 'name', 'type')
            ->get()
            ->keyBy('trace_id')
            ->map(fn ($row): array => ['name' => $row->name, 'type' => $row->type])
            ->all();
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
