<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\RouteFingerprint;
use App\Watch\Ingestion\Support\RouteName;
use App\Watch\Projects\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;

/**
 * One request span's full detail, addressed by trace id + span id -- the
 * same per-occurrence shape RouteOccurrencesQuery builds for one entry in
 * a route's "Recent requests" list, plus enough route context
 * (path/fingerprint/route_name/action) to say which route it belongs to.
 * Backs clicking a specific request out of a route's own list (see
 * ShowRequestOccurrenceController).
 */
class RequestOccurrenceQuery
{
    public function __construct(
        private readonly RouteRequestActivityQuery $routeRequestActivityQuery,
    ) {}

    /**
     * @return array{
     *     path: string|null,
     *     fingerprint: string|null,
     *     route_name: string|null,
     *     action: string|null,
     *     time: string,
     *     method: string|null,
     *     status_code: int|null,
     *     duration_ms: float,
     *     url: string|null,
     *     client_address: string|null,
     *     request_size: int|null,
     *     response_size: int|null,
     *     peak_memory: int|null,
     *     user: array{id: string|null, name: string|null, email: string|null}|null,
     *     trace_id: string|null,
     *     span_id: string|null,
     *     activity: array{
     *         queries: array{count: int, total_ms: float},
     *         outgoing_requests: array{count: int},
     *         queued_jobs: array{count: int},
     *         cache: array{hits: int, misses: int, writes: int},
     *         mail: array{count: int},
     *         notifications: array{count: int},
     *     },
     *     storage: array{reads: int, writes: int},
     *     hydrated_models: int,
     * }|null
     */
    public function find(Project $project, string $traceId, string $spanId): ?array
    {
        return $this->build($project, fn (Builder $query) => $query
            ->where('trace_id', $traceId)
            ->where('span_id', $spanId)
            ->first());
    }

    /**
     * @param  callable(Builder): (object|null)  $resolveRow
     * @return array<string, mixed>|null
     */
    private function build(Project $project, callable $resolveRow): ?array
    {
        $query = OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'request')
            ->whereNotNull('name')
            ->whereNotNull('duration_nanos')
            ->select('time', 'name', 'duration_nanos', 'attributes', 'trace_id', 'span_id');

        $row = $resolveRow($query);

        if ($row === null) {
            return null;
        }

        [$method, $path] = RouteName::split((string) $row->name);

        $attributes = json_decode((string) $row->attributes, true) ?? [];

        $routeName = $attributes['isoxen.route.name'] ?? null;
        $routeName = is_string($routeName) && $routeName !== '' ? $routeName : null;

        $action = $attributes['code.function.name'] ?? null;
        $action = is_string($action) && $action !== '' ? $action : null;

        $status = $attributes['http.response.status_code'] ?? null;
        $status = is_numeric($status) ? (int) $status : null;

        $url           = $attributes['url.full']       ?? null;
        $clientAddress = $attributes['client.address'] ?? null;

        $requestSize  = $attributes['http.request.body.size']  ?? null;
        $responseSize = $attributes['http.response.body.size'] ?? null;
        $peakMemory   = $attributes['isoxen.request.peak_memory'] ?? null;

        $storageReads   = $attributes['isoxen.request.storage_reads']   ?? null;
        $storageWrites  = $attributes['isoxen.request.storage_writes']  ?? null;
        $hydratedModels = $attributes['isoxen.request.hydrated_models'] ?? null;

        $traceId = is_string($row->trace_id) ? $row->trace_id : null;

        $activity   = $traceId !== null ? $this->routeRequestActivityQuery->execute($project, [$traceId]) : [];
        $noActivity = [
            'queries'           => ['count' => 0, 'total_ms' => 0.0],
            'outgoing_requests' => ['count' => 0],
            'queued_jobs'       => ['count' => 0],
            'cache'             => ['hits' => 0, 'misses' => 0, 'writes' => 0],
            'mail'              => ['count' => 0],
            'notifications'     => ['count' => 0],
        ];

        return [
            'path'            => $path,
            'fingerprint'     => $path !== null ? RouteFingerprint::make($path) : null,
            'route_name'      => $routeName,
            'action'          => $action,
            'time'            => Date::parse((string) $row->time)->toIso8601String(),
            'method'          => $method,
            'status_code'     => $status,
            'duration_ms'     => round(((int) $row->duration_nanos) / 1_000_000, 2),
            'url'             => is_string($url) && $url !== '' ? $url : null,
            'client_address'  => is_string($clientAddress) && $clientAddress !== '' ? $clientAddress : null,
            'request_size'    => is_numeric($requestSize) ? (int) $requestSize : null,
            'response_size'   => is_numeric($responseSize) ? (int) $responseSize : null,
            'peak_memory'     => is_numeric($peakMemory) ? (int) $peakMemory : null,
            'user'            => $this->extractUser($attributes),
            'trace_id'        => $traceId,
            'span_id'         => is_string($row->span_id) ? $row->span_id : null,
            'activity'        => $traceId !== null ? ($activity[$traceId] ?? $noActivity) : $noActivity,
            'storage'         => [
                'reads'  => is_numeric($storageReads) ? (int) $storageReads : 0,
                'writes' => is_numeric($storageWrites) ? (int) $storageWrites : 0,
            ],
            'hydrated_models' => is_numeric($hydratedModels) ? (int) $hydratedModels : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{id: string|null, name: string|null, email: string|null}|null
     */
    private function extractUser(array $attributes): ?array
    {
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
}
