<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Database\Query\Builder;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class CategorySummaryQuery
{
    /**
     * @return array{total: int, errors: int|null, slowest_ms: float|null, avg_ms: float|null, hours: int}
     */
    public function execute(Project $project, string $table, ?string $type = null, int $hours = 24): array
    {
        $since = \Illuminate\Support\Facades\Date::now()->subHours($hours);

        $total = $this->base($project, $table, $type, $since)->count();

        return [
            'total'      => $total,
            'errors'     => $this->errors($project, $table, $type, $since),
            'slowest_ms' => $table === 'otel_spans'
                ? $this->p95Milliseconds($project, $type, $since, $total)
                : null,
            'avg_ms' => $table === 'otel_spans'
                ? $this->avgMilliseconds($project, $type, $since)
                : null,
            'hours' => $hours,
        ];
    }

    private function errors(Project $project, string $table, ?string $type, DateTimeInterface $since): ?int
    {
        return match ($table) {
            // OTEL status code 2 is ERROR.
            'otel_spans' => $this->base($project, $table, $type, $since)->where('status_code', 2)->count(),
            // OTEL severity number 17 is where ERROR begins.
            'otel_logs' => $this->base($project, $table, $type, $since)->where('severity_number', '>=', 17)->count(),
            default     => null,
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

        $nanos = $this->base($project, 'otel_spans', $type, $since)
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
        $avgNanos = $this->base($project, 'otel_spans', $type, $since)
            ->whereNotNull('duration_nanos')
            ->avg('duration_nanos');

        return $avgNanos === null ? null : round((float) $avgNanos / 1_000_000, 1);
    }

    private function base(Project $project, string $table, ?string $type, DateTimeInterface $since): Builder
    {
        return DB::table($table)
            ->where('project_id', $project->id)
            ->where('time', '>=', $since)
            ->when($type !== null && $table === 'otel_spans', fn ($query) => $query->where('type', $type));
    }
}
