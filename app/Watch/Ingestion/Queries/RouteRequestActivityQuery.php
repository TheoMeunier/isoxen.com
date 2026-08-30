<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;

/**
 * What a handful of specific requests triggered downstream -- queries,
 * outgoing HTTP calls, dispatched jobs, cache operations, mail and
 * notifications -- read back from the child spans that share each
 * request's trace id. Used by RouteOccurrencesQuery to annotate the
 * Requests page's per-occurrence detail (see Nightwatch's own request
 * page, which this mirrors), one query for however many trace ids are
 * asked for rather than one per occurrence.
 *
 * Deliberately keyed by trace id and not folded into
 * RouteOccurrencesQuery itself: that class already does one scan to find
 * *which* requests match a route, this does a second, unrelated scan over
 * *their* child spans, and each reads more clearly kept apart.
 */
class RouteRequestActivityQuery
{
    /**
     * Only these categories roll up into the per-request activity summary
     * -- the rest (requests, exceptions, users, ...) either can't nest
     * inside a request's own trace or aren't part of Nightwatch's
     * equivalent breakdown.
     *
     * @var list<string>
     */
    private const array TYPES = ['query', 'outgoing_request', 'job', 'cache', 'mail', 'notification'];

    /**
     * The `kind` column stores each span's OTLP wire value (see
     * opentelemetry.proto.trace.v1.Span.SpanKind), not the OpenTelemetry
     * PHP SDK's own API\Trace\SpanKind interface -- the two enums number
     * PRODUCER differently (4 on the wire vs. 3 in the SDK interface), so
     * this is deliberately a literal rather than that interface's
     * constant. Mirrors trace.tsx's SPAN_KINDS map, which reads the same
     * column the same way.
     */
    private const int PRODUCER_KIND = 4;

    /**
     * @param  list<string>  $traceIds
     * @return array<string, array{
     *     queries: array{count: int, total_ms: float},
     *     outgoing_requests: array{count: int},
     *     queued_jobs: array{count: int},
     *     cache: array{hits: int, misses: int, writes: int},
     *     mail: array{count: int},
     *     notifications: array{count: int},
     * }>
     */
    public function execute(Project $project, array $traceIds): array
    {
        if ($traceIds === []) {
            return [];
        }

        /** @var array<string, array{queries: array{count: int, total_ms: float}, outgoing_requests: array{count: int}, queued_jobs: array{count: int}, cache: array{hits: int, misses: int, writes: int}, mail: array{count: int}, notifications: array{count: int}}> $activity */
        $activity = [];

        $blank = fn (): array => [
            'queries'           => ['count' => 0, 'total_ms' => 0.0],
            'outgoing_requests' => ['count' => 0],
            'queued_jobs'       => ['count' => 0],
            'cache'             => ['hits' => 0, 'misses' => 0, 'writes' => 0],
            'mail'              => ['count' => 0],
            'notifications'     => ['count' => 0],
        ];

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->whereIn('trace_id', $traceIds)
            ->whereIn('type', self::TYPES)
            ->select('trace_id', 'type', 'kind', 'duration_nanos', 'attributes')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$activity, $blank): void {
                foreach ($rows as $row) {
                    $traceId = $row->trace_id;

                    if (! is_string($traceId)) {
                        continue;
                    }

                    $activity[$traceId] ??= $blank();

                    match ($row->type) {
                        'query' => $this->recordQuery($activity[$traceId], $row->duration_nanos),
                        'outgoing_request' => $activity[$traceId]['outgoing_requests']['count']++,
                        // "process" spans (SPAN_KIND_CONSUMER) are the job
                        // actually running, usually well after -- and
                        // outside -- this request; only the "send" span
                        // (SPAN_KIND_PRODUCER, see QueueInstrumentation)
                        // marks a job dispatched during it.
                        'job' => $this->recordJob($activity[$traceId], $row->kind),
                        'cache' => $this->recordCache($activity[$traceId], $row->attributes),
                        'mail' => $activity[$traceId]['mail']['count']++,
                        'notification' => $activity[$traceId]['notifications']['count']++,
                        default => null,
                    };
                }
            });

        return $activity;
    }

    /**
     * @param  array{queries: array{count: int, total_ms: float}, outgoing_requests: array{count: int}, queued_jobs: array{count: int}, cache: array{hits: int, misses: int, writes: int}, mail: array{count: int}, notifications: array{count: int}}  $bucket
     */
    private function recordQuery(array &$bucket, mixed $durationNanos): void
    {
        $bucket['queries']['count']++;

        if (is_numeric($durationNanos)) {
            $bucket['queries']['total_ms'] = round($bucket['queries']['total_ms'] + ((float) $durationNanos / 1_000_000), 2);
        }
    }

    /**
     * @param  array{queries: array{count: int, total_ms: float}, outgoing_requests: array{count: int}, queued_jobs: array{count: int}, cache: array{hits: int, misses: int, writes: int}, mail: array{count: int}, notifications: array{count: int}}  $bucket
     */
    private function recordJob(array &$bucket, mixed $kind): void
    {
        if (is_numeric($kind) && (int) $kind === self::PRODUCER_KIND) {
            $bucket['queued_jobs']['count']++;
        }
    }

    /**
     * @param  array{queries: array{count: int, total_ms: float}, outgoing_requests: array{count: int}, queued_jobs: array{count: int}, cache: array{hits: int, misses: int, writes: int}, mail: array{count: int}, notifications: array{count: int}}  $bucket
     */
    private function recordCache(array &$bucket, mixed $rawAttributes): void
    {
        $attributes = json_decode((string) $rawAttributes, true) ?? [];
        $operation  = $attributes['cache.operation'] ?? null;

        match ($operation) {
            'hit'   => $bucket['cache']['hits']++,
            'miss'  => $bucket['cache']['misses']++,
            'write' => $bucket['cache']['writes']++,
            default => null,
        };
    }
}
