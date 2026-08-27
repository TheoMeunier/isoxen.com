<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Models\Project;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/**
 * The Overview tab's one-screen health check: every category's 24h volume and error count.
 *
 * "Error" is deliberately defined per category the same way its own page defines it (see
 * ShowProjectController::statusBreakdown()), rather than a single generic rule, so this summary never
 * disagrees with the page it links to:
 *  - Requests counts only 5XX responses -- a 4XX is usually the caller's fault, not the app's.
 *  - Logs counts entries at Error severity or above.
 *  - Exceptions counts every entry: being in this category *is* the error, regardless of what OTEL
 *    status the SDK happened to set on the span.
 *  - Every other span category (jobs, commands, queries, ...) counts spans with an OTEL error status.
 *  - Metrics has no notion of failure, so it's volume-only.
 */
class ProjectHealthOverviewQuery
{
    public function __construct(
        private readonly RequestStatusBreakdownQuery $requestStatusBreakdownQuery,
        private readonly LogSeverityBreakdownQuery $logSeverityBreakdownQuery,
    ) {}

    /**
     * @return array<int, array{slug: string, label: string, group: string, total: int, errors: int}>
     */
    public function execute(Project $project, int $hours = 24): array
    {
        $since = Date::now()->subHours($hours);

        $overview = [];

        foreach (ObservabilityCategories::all() as $slug => $category) {
            // Users isn't a volume/error category -- it's the live-users list, shown separately.
            // A disabled category ("coming soon" in the sidebar) has nothing to summarize yet either.
            if ($slug === 'users' || ! $category['enabled']) {
                continue;
            }

            $model = ObservabilityCategories::model($slug);
            $total = $this->total($project, $model, $category['type'], $since);

            $overview[] = [
                'slug'   => $slug,
                'label'  => $category['label'],
                'group'  => $category['group'],
                'total'  => $total,
                'errors' => $this->errors($project, $slug, $model, $category['type'], $since, $hours, $total),
            ];
        }

        return $overview;
    }

    /**
     * @param  class-string<\App\Watch\Ingestion\Models\OtelRecord>  $model
     */
    private function total(Project $project, string $model, ?string $type, DateTimeInterface $since): int
    {
        return $model::query()->toBase()
            ->where('project_id', $project->id)
            ->where('time', '>=', $since)
            ->when($type !== null && $model === OtelSpan::class, fn ($query) => $query->where('type', $type))
            ->count();
    }

    /**
     * @param  class-string<\App\Watch\Ingestion\Models\OtelRecord>  $model
     */
    private function errors(Project $project, string $slug, string $model, ?string $type, DateTimeInterface $since, int $hours, int $total): int
    {
        return match (true) {
            $slug === 'requests' => $this->requestStatusBreakdownQuery->execute($project, $hours)['server_error'],
            $slug === 'logs' => $this->logSeverityBreakdownQuery->execute($project, $hours)['error'],
            $slug === 'exceptions' => $total,
            $model !== OtelSpan::class => 0,
            default => OtelSpan::query()->toBase()
                ->where('project_id', $project->id)
                ->where('time', '>=', $since)
                ->when($type !== null, fn ($query) => $query->where('type', $type))
                ->where('status_code', OtelSpan::STATUS_ERROR)
                ->count(),
        };
    }
}
