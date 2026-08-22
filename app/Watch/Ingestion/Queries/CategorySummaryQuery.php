<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelLog;
use App\Watch\Ingestion\Models\OtelRecord;
use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;

class CategorySummaryQuery
{
    /**
     * @param  class-string<OtelRecord>  $model
     * @return array{total: int, errors: int|null, slowest_ms: float|null, avg_ms: float|null, hours: int}
     */
    public function execute(Project $project, string $model, ?string $type = null, int $hours = 24): array
    {
        $since = \Illuminate\Support\Facades\Date::now()->subHours($hours);

        $total = $this->base($project, $model, $type, $since)->count();

        return [
            'total'      => $total,
            'errors'     => $this->errors($project, $model, $type, $since),
            'slowest_ms' => $model === OtelSpan::class
                ? $this->p95Milliseconds($project, $type, $since, $total)
                : null,
            'avg_ms' => $model === OtelSpan::class
                ? $this->avgMilliseconds($project, $type, $since)
                : null,
            'hours' => $hours,
        ];
    }

    /**
     * @param  class-string<OtelRecord>  $model
     */
    private function errors(Project $project, string $model, ?string $type, DateTimeInterface $since): ?int
    {
        return match ($model) {
            OtelSpan::class => $this->base($project, $model, $type, $since)->where('status_code', OtelSpan::STATUS_ERROR)->count(),
            OtelLog::class  => $this->base($project, $model, $type, $since)->where('severity_number', '>=', OtelLog::SEVERITY_ERROR)->count(),
            default         => null,
        };
    }

    /**
     * The 95th percentile duration in milliseconds, read by order-and-offset since SQLite has no `percentile_cont`.
     */
    private function p95Milliseconds(Project $project, ?string $type, DateTimeInterface $since, int $total): ?float
    {
        if ($total === 0) {
            return null;
        }

        $nanos = $this->base($project, OtelSpan::class, $type, $since)
            ->whereNotNull('duration_nanos')
            ->orderBy('duration_nanos')
            ->offset((int) floor($total * 0.95))
            ->limit(1)
            ->value('duration_nanos');

        return $nanos === null ? null : round((int) $nanos / 1_000_000, 1);
    }

    /**
     * The average duration in milliseconds, the Duration panel's headline figure alongside the p95.
     */
    private function avgMilliseconds(Project $project, ?string $type, DateTimeInterface $since): ?float
    {
        $avgNanos = $this->base($project, OtelSpan::class, $type, $since)
            ->whereNotNull('duration_nanos')
            ->avg('duration_nanos');

        return $avgNanos === null ? null : round((float) $avgNanos / 1_000_000, 1);
    }

    /**
     * @param  class-string<OtelRecord>  $model
     */
    private function base(Project $project, string $model, ?string $type, DateTimeInterface $since): Builder
    {
        return $model::query()->toBase()
            ->where('project_id', $project->id)
            ->where('time', '>=', $since)
            ->when($type !== null && $model === OtelSpan::class, fn ($query) => $query->where('type', $type));
    }
}
