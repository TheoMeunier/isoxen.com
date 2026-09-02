<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Data\QueryDetail;
use App\Watch\Ingestion\Data\QueryOccurrence;
use App\Watch\Ingestion\Data\QueryTimelinePoint;
use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\QueryFingerprint;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\Date;

class QueryOccurrencesQuery
{
    private const int MAX_OCCURRENCES = 50;

    public function execute(Project $project, string $fingerprint, int $hours = 24): ?QueryDetail
    {
        $since = Date::now('UTC')->subHours($hours - 1)->startOfHour();

        $buckets = [];
        for ($hour = 0; $hour < $hours; $hour++) {
            $at = $since->copy()->addHours($hour);
            $buckets[$at->format('Y-m-d H:00')] = [
                'at' => $at->toIso8601String(),
                'calls' => 0,
                'duration_nanos' => 0,
                'durations' => [],
            ];
        }

        $text = null;
        $system = null;
        $namespace = null;
        $host = null;
        $port = null;
        $firstSeen = null;
        $lastSeen = null;

        $durations = [];

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
                    $attributes = json_decode((string)$row->attributes, true) ?? [];

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
                    $port ??= is_numeric($rowPort) ? (int)$rowPort : null;

                    $time = Date::parse((string)$row->time);
                    $durationNs = (int)$row->duration_nanos;

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

                    $caller = $attributes['code.function.name'] ?? null;
                    $callerFile = $attributes['code.file.path'] ?? null;
                    $callerLine = $attributes['code.line.number'] ?? null;

                    $occurrences[] = [
                        'time' => $time,
                        'duration_ms' => round($durationNs / 1_000_000, 2),
                        'trace_id' => is_string($row->trace_id) ? $row->trace_id : null,
                        'span_id' => is_string($row->span_id) ? $row->span_id : null,
                        'caller' => is_string($caller) && $caller !== '' ? $caller : null,
                        'caller_file' => is_string($callerFile) && $callerFile !== '' ? $callerFile : null,
                        'caller_line' => is_numeric($callerLine) ? (int)$callerLine : null,
                    ];

                    if (count($occurrences) > self::MAX_OCCURRENCES) {
                        array_shift($occurrences);
                    }
                }
            });

        if ($text === null || $firstSeen === null || $lastSeen === null) {
            return null;
        }

        sort($durations);
        $calls = count($durations);
        $totalNanos = array_sum($durations);

        $recentFirst = array_reverse($occurrences);
        $triggers = $this->resolveTriggers($project, $recentFirst);

        return new QueryDetail(
            query: $text,
            fingerprint: $fingerprint,
            system: $system,
            namespace: $namespace,
            host: $host,
            port: $port,
            calls: $calls,
            total_ms: round($totalNanos / 1_000_000, 1),
            avg_ms: $calls > 0 ? round($totalNanos / $calls / 1_000_000, 1) : 0.0,
            p95_ms: $this->percentile($durations, 0.95),
            first_seen: $firstSeen->toIso8601String(),
            last_seen: $lastSeen->toIso8601String(),
            timeline: array_values(array_map(function (array $bucket): QueryTimelinePoint {
                $bucketDurations = $bucket['durations'];
                sort($bucketDurations);

                return new QueryTimelinePoint(
                    at: $bucket['at'],
                    calls: $bucket['calls'],
                    avg_ms: $bucket['calls'] > 0 ? round($bucket['duration_nanos'] / $bucket['calls'] / 1_000_000, 1) : null,
                    p95_ms: $bucketDurations === [] ? null : $this->percentile($bucketDurations, 0.95),
                );
            }, $buckets)),
            occurrences: array_map(fn(array $occurrence): QueryOccurrence => new QueryOccurrence(
                time: $occurrence['time']->toIso8601String(),
                duration_ms: $occurrence['duration_ms'],
                trace_id: $occurrence['trace_id'],
                span_id: $occurrence['span_id'],
                caller: $occurrence['caller'],
                caller_file: $occurrence['caller_file'],
                caller_line: $occurrence['caller_line'],
                called_from: $occurrence['trace_id'] !== null ? ($triggers[$occurrence['trace_id']]['name'] ?? null) : null,
                called_from_type: $occurrence['trace_id'] !== null ? ($triggers[$occurrence['trace_id']]['type'] ?? null) : null,
            ), $recentFirst),
        );
    }

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
            ->map(fn($row): array => ['name' => $row->name, 'type' => $row->type])
            ->all();
    }

    private function percentile(array $sortedDurationsNanos, float $fraction): float
    {
        $total = count($sortedDurationsNanos);

        if ($total === 0) {
            return 0.0;
        }

        $offset = min((int)floor($total * $fraction), $total - 1);

        return round($sortedDurationsNanos[$offset] / 1_000_000, 1);
    }
}
