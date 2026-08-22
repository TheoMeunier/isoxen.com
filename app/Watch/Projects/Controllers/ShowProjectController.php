<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\ActivityTimelineQuery;
use App\Watch\Ingestion\Queries\CategorySummaryQuery;
use App\Watch\Ingestion\Queries\DurationTimelineQuery;
use App\Watch\Ingestion\Queries\LogSeverityBreakdownQuery;
use App\Watch\Ingestion\Queries\OnlineUsersQuery;
use App\Watch\Ingestion\Queries\RecentLogsQuery;
use App\Watch\Ingestion\Queries\RecentMetricsQuery;
use App\Watch\Ingestion\Queries\RecentSpansQuery;
use App\Watch\Ingestion\Queries\RequestStatusBreakdownQuery;
use App\Watch\Ingestion\Queries\SlowEndpointsQuery;
use App\Watch\Ingestion\Queries\StatusTimelineQuery;
use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowProjectController extends Controller
{
    /**
     * The label pair a category's generic two-way breakdown uses for its
     * "healthy" and "unhealthy" segments. Requests and Logs don't appear
     * here -- they get a real category-specific breakdown (HTTP status
     * class, log severity) from their own query instead of this generic
     * Ok/Error split.
     *
     * @var array<string, array{success: string, failure: string}>
     */
    private const STATUS_LABELS = [
        'jobs'     => ['success' => 'Processed', 'failure' => 'Failed'],
        'commands' => ['success' => 'Successful', 'failure' => 'Unsuccessful'],
    ];

    private const DEFAULT_STATUS_LABELS = ['success' => 'Successful', 'failure' => 'Errors'];

    public function __construct(
        private readonly RecentSpansQuery $recentSpansQuery,
        private readonly RecentMetricsQuery $recentMetricsQuery,
        private readonly RecentLogsQuery $recentLogsQuery,
        private readonly ActivityTimelineQuery $activityTimelineQuery,
        private readonly DurationTimelineQuery $durationTimelineQuery,
        private readonly CategorySummaryQuery $categorySummaryQuery,
        private readonly SlowEndpointsQuery $slowEndpointsQuery,
        private readonly RequestStatusBreakdownQuery $requestStatusBreakdownQuery,
        private readonly LogSeverityBreakdownQuery $logSeverityBreakdownQuery,
        private readonly StatusTimelineQuery $statusTimelineQuery,
        private readonly OnlineUsersQuery $onlineUsersQuery,
    ) {}

    /**
     * A bare project URL -- no category segment, e.g. an old bookmark or a
     * link built before categories moved into the path -- redirects to the
     * project's default category instead of 404ing.
     */
    public function redirectToDefaultCategory(Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        return redirect()->route('projects.show', [
            'project'  => $project,
            'category' => ObservabilityCategories::default(),
        ]);
    }

    /**
     * Render the project's active category (Requests, Jobs, Queries, ..., or
     * the pseudo-categories Information/Users), dispatching to whichever of
     * the three page templates that category needs.
     */
    public function render(Request $request, Project $project, string $category): Response
    {
        $this->authorize('view', $project);

        // Information isn't an observability category -- there's no span,
        // log, or metric behind it, just the project's own settings (rename,
        // ingestion token, delete). ObservabilityCategories doesn't know
        // about it for that reason, so it's handled before the validity
        // check below rather than being taught to that class.
        if ($category === 'information') {
            return $this->renderInformation($project);
        }

        // The route's {category} constraint (see routes/projects.php)
        // already rejects anything not in ObservabilityCategories::all() or
        // 'information' above before a request gets here -- this is defense
        // in depth, not the primary guard.
        if (! ObservabilityCategories::isValid($category)) {
            abort(404);
        }

        if ($category === 'users') {
            return $this->renderUsers($project);
        }

        return $this->renderActivity($project, $category);
    }

    /**
     * The Information tab: the project's own settings. There's no telemetry
     * behind it, so none of the activity pipeline below runs and none of its
     * props are sent.
     */
    private function renderInformation(Project $project): Response
    {
        return Inertia::render('projects/show/information', [
            'project' => new ProjectResource($project),
        ]);
    }

    /**
     * The Users tab: who's currently online, nothing else. Polled on its own
     * (see projects/show/users.tsx, `router.reload({ only: ['onlineUsers'] })`),
     * which is cheap now that this route only ever computes this one query.
     */
    private function renderUsers(Project $project): Response
    {
        return Inertia::render('projects/show/users', [
            'project'     => new ProjectResource($project),
            'onlineUsers' => fn () => $this->onlineUsersQuery->execute($project),
        ]);
    }

    /**
     * The 11 telemetry categories (Requests, Jobs, Commands, Scheduled
     * Tasks, Exceptions, Queries, Notifications, Mail, Cache, Outgoing
     * Requests, Metrics): the shared entries/summary/timeline pipeline every
     * one of them renders through the same activity template.
     *
     * `currentProject` and `categoryCounts` power the app sidebar and are
     * shared for every project-bound route by HandleInertiaRequests, so they
     * aren't repeated here.
     */
    private function renderActivity(Project $project, string $categorySlug): Response
    {
        $category = ObservabilityCategories::get($categorySlug);
        $table    = ObservabilityCategories::table($categorySlug);

        // Memoized rather than called directly from both the `summary` and
        // `statusBreakdown` closures below: without this they'd each
        // re-execute CategorySummaryQuery.
        $summary        = null;
        $resolveSummary = function () use (&$summary, $project, $table, $category) {
            return $summary ??= $this->categorySummaryQuery->execute($project, $table, $category['type']);
        };

        return Inertia::render('projects/show/activity', [
            'project'        => new ProjectResource($project),
            'activeCategory' => $categorySlug,
            'entries'        => fn () => match ($category['source']) {
                'metrics' => $this->recentMetricsQuery->execute($project),
                'logs'    => $this->recentLogsQuery->execute($project),
                default   => $this->recentSpansQuery->execute($project, $category['type']),
            },
            'summary'  => $resolveSummary,
            'timeline' => fn () => $this->activityTimelineQuery->execute($project, $table, $category['type']),
            // Duration only means anything for spans -- logs and metrics
            // don't carry a `duration_nanos` column.
            'durationTimeline' => fn () => $table === 'otel_spans'
                ? $this->durationTimelineQuery->execute($project, $category['type'])
                : [],
            'statusBreakdown' => fn () => $this->statusBreakdown($project, $categorySlug, $table, $resolveSummary()),
            'statusTimeline'  => fn () => $this->statusTimeline($project, $categorySlug, $table, $category['type']),
            // The endpoint breakdown only makes sense for Requests -- other
            // categories don't have a stable "name" that's worth grouping
            // by (a query's text, a job's name) the way an endpoint route
            // is, and computing it costs several extra queries (see
            // SlowEndpointsQuery), so it's skipped entirely otherwise.
            'slowEndpoints' => fn () => $categorySlug === 'requests'
                ? $this->slowEndpointsQuery->execute($project)
                : collect(),
        ]);
    }

    /**
     * The pills shown next to a category's headline count -- what "healthy"
     * vs "unhealthy" means for this category, and how many of each.
     *
     * Requests and Logs get a real breakdown specific to what they actually
     * track (HTTP status class, log severity). Every other span category
     * falls back to the generic Ok/Error split CategorySummaryQuery already
     * computed for its "errors" stat tile -- reusing it here rather than
     * running a second query for the same count. Metrics has no notion of
     * success/failure, so it gets nothing.
     *
     * @return array<int, array{label: string, value: int, tone: string}>
     */
    private function statusBreakdown(Project $project, string $categorySlug, string $table, array $summary): array
    {
        if ($categorySlug === 'requests') {
            $counts = $this->requestStatusBreakdownQuery->execute($project);

            return [
                ['label' => '1XX-3XX', 'value' => $counts['success'], 'tone' => 'neutral'],
                ['label' => '4XX', 'value' => $counts['client_error'], 'tone' => 'warning'],
                ['label' => '5XX', 'value' => $counts['server_error'], 'tone' => 'critical'],
            ];
        }

        if ($categorySlug === 'logs') {
            $counts = $this->logSeverityBreakdownQuery->execute($project);

            return [
                ['label' => 'Info', 'value' => $counts['info'], 'tone' => 'neutral'],
                ['label' => 'Warning', 'value' => $counts['warning'], 'tone' => 'warning'],
                ['label' => 'Error', 'value' => $counts['error'], 'tone' => 'critical'],
            ];
        }

        if ($table !== 'otel_spans' || $summary['errors'] === null) {
            return [];
        }

        $labels = self::STATUS_LABELS[$categorySlug] ?? self::DEFAULT_STATUS_LABELS;

        return [
            ['label' => $labels['success'], 'value' => $summary['total'] - $summary['errors'], 'tone' => 'neutral'],
            ['label' => $labels['failure'], 'value' => $summary['errors'], 'tone' => 'critical'],
        ];
    }

    /**
     * The same breakdown as statusBreakdown(), per hour -- what the volume
     * chart's stacked bars are made of. Every segment here carries the same
     * label/tone as its counterpart pill above the chart, so a bar's colour
     * always means the same thing as the legend reading it.
     *
     * @return array<int, array{at: string, segments: array<int, array{label: string, value: int, tone: string}>}>
     */
    private function statusTimeline(Project $project, string $categorySlug, string $table, ?string $type): array
    {
        if ($categorySlug === 'requests') {
            return array_map(fn (array $hour): array => [
                'at'       => $hour['at'],
                'segments' => [
                    ['label' => '1XX-3XX', 'value' => $hour['success'], 'tone' => 'neutral'],
                    ['label' => '4XX', 'value' => $hour['client_error'], 'tone' => 'warning'],
                    ['label' => '5XX', 'value' => $hour['server_error'], 'tone' => 'critical'],
                ],
            ], $this->requestStatusBreakdownQuery->executeTimeline($project));
        }

        if ($categorySlug === 'logs') {
            return array_map(fn (array $hour): array => [
                'at'       => $hour['at'],
                'segments' => [
                    ['label' => 'Info', 'value' => $hour['info'], 'tone' => 'neutral'],
                    ['label' => 'Warning', 'value' => $hour['warning'], 'tone' => 'warning'],
                    ['label' => 'Error', 'value' => $hour['error'], 'tone' => 'critical'],
                ],
            ], $this->logSeverityBreakdownQuery->executeTimeline($project));
        }

        if ($table !== 'otel_spans') {
            return [];
        }

        $labels = self::STATUS_LABELS[$categorySlug] ?? self::DEFAULT_STATUS_LABELS;

        return array_map(fn (array $hour): array => [
            'at'       => $hour['at'],
            'segments' => [
                ['label' => $labels['success'], 'value' => $hour['success'], 'tone' => 'neutral'],
                ['label' => $labels['failure'], 'value' => $hour['error'], 'tone' => 'critical'],
            ],
        ], $this->statusTimelineQuery->execute($project, $type));
    }
}
