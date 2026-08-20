<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The headline numbers shown above a category's table.
 *
 * Scoped to the same window as the chart beside them. That's partly
 * honesty — an all-time total sitting next to a 24-hour chart invites the
 * reader to compare two different things — and partly cost: every one of
 * these figures would otherwise scan a table that only ever grows.
 */
class CategorySummaryQuery
{
    /**
     * @return array{total: int, errors: int|null, slowest_ms: float|null, hours: int}
     */
    public function execute(Project $project, string $table, ?string $type = null, int $hours = 24): array
    {
        $since = Carbon::now()->subHours($hours);

        $total = $this->base($project, $table, $type, $since)->count();

        return [
            'total' => $total,
            'errors' => $this->errors($project, $table, $type, $since),
            'slowest_ms' => $table === 'otel_spans'
                ? $this->p95Milliseconds($project, $type, $since, $total)
                : null,
            'hours' => $hours,
        ];
    }

    private function errors(Project $project, string $table, ?string $type, Carbon $since): ?int
    {
        return match ($table) {
            // OTEL status code 2 is ERROR.
            'otel_spans' => $this->base($project, $table, $type, $since)->where('status_code', 2)->count(),
            // OTEL severity number 17 is where ERROR begins.
            'otel_logs' => $this->base($project, $table, $type, $since)->where('severity_number', '>=', 17)->count(),
            default => null,
        };
    }

    /**
     * The 95th percentile duration, in milliseconds.
     *
     * Read by ordering and offsetting rather than with a percentile
     * function, because `percentile_cont` doesn't exist on SQLite and the
     * test suite runs there. That's a sort, so it only stays cheap because
     * the window above bounds how many rows it can touch — don't widen the
     * window without revisiting this.
     *
     * The p95 is used in preference to an average, which a handful of slow
     * outliers would hide.
     */
    private function p95Milliseconds(Project $project, ?string $type, Carbon $since, int $total): ?float
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

    private function base(Project $project, string $table, ?string $type, Carbon $since): Builder
    {
        return DB::table($table)
            ->where('project_id', $project->id)
            ->where('time', '>=', $since)
            ->when($type !== null && $table === 'otel_spans', fn ($query) => $query->where('type', $type));
    }
}
