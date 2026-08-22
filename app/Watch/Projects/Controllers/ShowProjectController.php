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
     * Label pairs for a category's generic Ok/Error breakdown; Requests and Logs use their own instead.
     *
     * @var array<string, array{success: string, failure: string}>
     */
    private const STATUS_LABELS = [
        'jobs' => ['success' => 'Processed', 'failure' => 'Failed'],
        'commands' => ['success' => 'Successful', 'failure' => 'Unsuccessful'],
    ];

    private const DEFAULT_STATUS_LABELS = ['success' => 'Successful', 'failure' => 'Errors'];

    public function __construct(
        private readonly RecentSpansQuery            $recentSpansQuery,
        private readonly RecentMetricsQuery          $recentMetricsQuery,
        private readonly RecentLogsQuery             $recentLogsQuery,
        private readonly ActivityTimelineQuery       $activityTimelineQuery,
        private readonly DurationTimelineQuery       $durationTimelineQuery,
        private readonly CategorySummaryQuery        $categorySummaryQuery,
        private readonly SlowEndpointsQuery          $slowEndpointsQuery,
        private readonly RequestStatusBreakdownQuery $requestStatusBreakdownQuery,
        private readonly LogSeverityBreakdownQuery   $logSeverityBreakdownQuery,
        private readonly StatusTimelineQuery         $statusTimelineQuery,
        private readonly OnlineUsersQuery            $onlineUsersQuery,
    )
    {
    }

    public function redirectToDefaultCategory(Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        return redirect()->route('projects.show', [
            'project' => $project,
            'category' => ObservabilityCategories::default(),
        ]);
    }

    public function render(Request $request, Project $project, string $category): Response
    {
        $this->authorize('view', $project);

        if ($category === 'information') {
            return $this->renderInformation($project);
        }

        if (!ObservabilityCategories::isValid($category)) {
            abort(404);
        }

        if ($category === 'users') {
            return $this->renderUsers($project);
        }

        return $this->renderActivity($project, $category);
    }

    private function renderInformation(Project $project): Response
    {
        return Inertia::render('projects/show/information', [
            'project' => new ProjectResource($project),
        ]);
    }

    private function renderUsers(Project $project): Response
    {
        return Inertia::render('projects/show/users', [
            'project' => new ProjectResource($project),
            'onlineUsers' => fn() => $this->onlineUsersQuery->execute($project),
        ]);
    }

    private function renderActivity(Project $project, string $categorySlug): Response
    {
        $category = ObservabilityCategories::get($categorySlug);
        $table = ObservabilityCategories::table($categorySlug);

        $summary = null;
        $resolveSummary = function () use (&$summary, $project, $table, $category) {
            return $summary ??= $this->categorySummaryQuery->execute($project, $table, $category['type']);
        };

        return Inertia::render('projects/show/activity', [
            'project' => new ProjectResource($project),
            'activeCategory' => $categorySlug,
            'entries' => fn() => match ($category['source']) {
                'metrics' => $this->recentMetricsQuery->execute($project),
                'logs' => $this->recentLogsQuery->execute($project),
                default => $this->recentSpansQuery->execute($project, $category['type']),
            },
            'summary' => $resolveSummary,
            'timeline' => fn() => $this->activityTimelineQuery->execute($project, $table, $category['type']),
            'durationTimeline' => fn() => $table === 'otel_spans'
                ? $this->durationTimelineQuery->execute($project, $category['type'])
                : [],
            'statusBreakdown' => fn() => $this->statusBreakdown($project, $categorySlug, $table, $resolveSummary()),
            'statusTimeline' => fn() => $this->statusTimeline($project, $categorySlug, $table, $category['type']),
            'slowEndpoints' => fn() => $categorySlug === 'requests'
                ? $this->slowEndpointsQuery->execute($project)
                : collect(),
        ]);
    }

    /**
     * The pills next to a category's headline count: a bespoke breakdown for Requests and Logs, Ok/Error otherwise.
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
     * The same breakdown as {@see self::statusBreakdown()}, per hour, with matching labels and tones.
     *
     * @return array<int, array{at: string, segments: array<int, array{label: string, value: int, tone: string}>}>
     */
    private function statusTimeline(Project $project, string $categorySlug, string $table, ?string $type): array
    {
        if ($categorySlug === 'requests') {
            return array_map(fn(array $hour): array => [
                'at' => $hour['at'],
                'segments' => [
                    ['label' => '1XX-3XX', 'value' => $hour['success'], 'tone' => 'neutral'],
                    ['label' => '4XX', 'value' => $hour['client_error'], 'tone' => 'warning'],
                    ['label' => '5XX', 'value' => $hour['server_error'], 'tone' => 'critical'],
                ],
            ], $this->requestStatusBreakdownQuery->executeTimeline($project));
        }

        if ($categorySlug === 'logs') {
            return array_map(fn(array $hour): array => [
                'at' => $hour['at'],
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

        return array_map(fn(array $hour): array => [
            'at' => $hour['at'],
            'segments' => [
                ['label' => $labels['success'], 'value' => $hour['success'], 'tone' => 'neutral'],
                ['label' => $labels['failure'], 'value' => $hour['error'], 'tone' => 'critical'],
            ],
        ], $this->statusTimelineQuery->execute($project, $type));
    }
}
