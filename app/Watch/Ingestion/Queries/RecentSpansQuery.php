<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class RecentSpansQuery
{
    /**
     * Categories whose span `name` is too vague for the table and need a `detail` pulled out of `attributes`.
     *
     * @var list<string>
     */
    private const array DETAILED_TYPES = ['query', 'outgoing_request', 'cache'];

    public function execute(Project $project, ?string $type = null, int $perPage = 25): LengthAwarePaginator
    {
        $needsAttributes = $type === null || in_array($type, self::DETAILED_TYPES, true);

        return $project->spans()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('time')
            ->select([
                'time', 'end_time', 'name', 'type', 'kind', 'duration_nanos',
                'status_code', 'trace_id', 'span_id',
                ...($needsAttributes ? ['attributes'] : []),
            ])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (OtelSpan $span): OtelSpan => $this->withDetail($span));
    }

    private function withDetail(OtelSpan $span): OtelSpan
    {
        // The model-side equivalent of the old property_exists() guard: a column that was never
        // selected is absent from the attribute bag, and that category gets no `detail` key at all.
        if (! array_key_exists('attributes', $span->getAttributes())) {
            return $span;
        }

        $attributes = $span->attributes ?? [];

        $span->detail = match ($span->type) {
            'query'            => $attributes['db.query.text'] ?? null,
            'outgoing_request' => trim(sprintf(
                '%s %s',
                $attributes['http.request.method'] ?? '',
                $attributes['url.full']            ?? $span->name ?? ''
            )) ?: null,
            'cache' => trim(sprintf(
                '%s %s',
                $attributes['cache.operation'] ?? '',
                $attributes['cache.key']       ?? ''
            )) ?: null,
            default => null,
        };

        unset($span->attributes);

        return $span;
    }
}
