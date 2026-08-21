<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Queries\Concerns\ReturnsIsoTimestamps;
use App\Watch\Projects\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RecentSpansQuery
{
    use ReturnsIsoTimestamps;

    /**
     * Categories whose span `name` alone isn't informative enough for the
     * table. Queries only ever get "SELECT"/"INSERT"/... (the real SQL is
     * in attributes.db.query.text -- see QueryInstrumentation), Outgoing
     * Requests only get a bare verb unless the host app has wired up a
     * route-name resolver (attributes.url.full always has the real target
     * -- see GuzzleTraceMiddleware), and Cache only gets "cache
     * hit"/"cache write"/... with no indication of *which* key (that's
     * attributes.cache.key -- see CacheInstrumentation). All three need a
     * field pulled out of `attributes` into a `detail` column; nothing
     * else in that blob (it also carries request/response headers) is fit
     * to ship to the frontend, so it's decoded here and discarded once
     * `detail` is set.
     *
     * @var list<string>
     */
    private const DETAILED_TYPES = ['query', 'outgoing_request', 'cache'];

    public function execute(Project $project, ?string $type = null, int $perPage = 25): LengthAwarePaginator
    {
        $needsAttributes = $type === null || in_array($type, self::DETAILED_TYPES, true);

        return DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('time')
            ->select([
                'time', 'end_time', 'name', 'type', 'kind', 'duration_nanos',
                'status_code', 'trace_id', 'span_id',
                ...($needsAttributes ? ['attributes'] : []),
            ])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $row): object => $this->toIso($this->withDetail($row), ['time', 'end_time']));
    }

    private function withDetail(object $row): object
    {
        if (! property_exists($row, 'attributes')) {
            return $row;
        }

        $attributes = $row->attributes === null
            ? []
            : json_decode((string) $row->attributes, true);

        $row->detail = match ($row->type) {
            'query'            => $attributes['db.query.text'] ?? null,
            'outgoing_request' => trim(sprintf(
                '%s %s',
                $attributes['http.request.method'] ?? '',
                $attributes['url.full']            ?? $row->name ?? ''
            )) ?: null,
            'cache' => trim(sprintf(
                '%s %s',
                $attributes['cache.operation'] ?? '',
                $attributes['cache.key']       ?? ''
            )) ?: null,
            default => null,
        };

        unset($row->attributes);

        return $row;
    }
}
