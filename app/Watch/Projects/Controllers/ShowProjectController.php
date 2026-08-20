<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\ActivityTimelineQuery;
use App\Watch\Ingestion\Queries\CategorySummaryQuery;
use App\Watch\Ingestion\Queries\RecentLogsQuery;
use App\Watch\Ingestion\Queries\RecentMetricsQuery;
use App\Watch\Ingestion\Queries\RecentSpansQuery;
use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowProjectController extends Controller
{
    public function __construct(
        private readonly RecentSpansQuery $recentSpansQuery,
        private readonly RecentMetricsQuery $recentMetricsQuery,
        private readonly RecentLogsQuery $recentLogsQuery,
        private readonly ActivityTimelineQuery $activityTimelineQuery,
        private readonly CategorySummaryQuery $categorySummaryQuery,
    ) {}

    /**
     * Render the given project, including its ingestion token and the most
     * recent entries for the active sidebar category (Requests, Jobs,
     * Queries, ...).
     */
    public function render(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $categorySlug = $request->query('category');
        $categorySlug = is_string($categorySlug) && ObservabilityCategories::isValid($categorySlug)
            ? $categorySlug
            : ObservabilityCategories::default();

        $category = ObservabilityCategories::get($categorySlug);

        $entries = match ($category['source']) {
            'metrics' => $this->recentMetricsQuery->execute($project),
            'logs' => $this->recentLogsQuery->execute($project),
            default => $this->recentSpansQuery->execute($project, $category['type']),
        };

        $table = ObservabilityCategories::table($categorySlug);

        // `currentProject` and `categoryCounts` power the app sidebar and
        // are shared for every project-bound route by
        // HandleInertiaRequests, so they aren't repeated here.
        return Inertia::render('projects/show', [
            'project' => new ProjectResource($project),
            'activeCategory' => $categorySlug,
            'entries' => $entries,
            'summary' => $this->categorySummaryQuery->execute($project, $table, $category['type']),
            'timeline' => $this->activityTimelineQuery->execute($project, $table, $category['type']),
        ]);
    }
}
