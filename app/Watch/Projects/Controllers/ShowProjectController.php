<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\OnlineUsersQuery;
use App\Watch\Ingestion\Queries\ProjectHealthOverviewQuery;
use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Controllers\Cache\ListCacheController;
use App\Watch\Projects\Controllers\Commands\ListCommandsController;
use App\Watch\Projects\Controllers\Exceptions\ListExceptionsController;
use App\Watch\Projects\Controllers\Jobs\ListJobsController;
use App\Watch\Projects\Controllers\Logs\ListLogsController;
use App\Watch\Projects\Controllers\Mail\ListMailController;
use App\Watch\Projects\Controllers\Metrics\ListMetricsController;
use App\Watch\Projects\Controllers\Notifications\ListNotificationsController;
use App\Watch\Projects\Controllers\OutgoingRequests\ListOutgoingRequestsController;
use App\Watch\Projects\Controllers\Query\ListQueriesController;
use App\Watch\Projects\Controllers\Requests\ListRequestsController;
use App\Watch\Projects\Controllers\ScheduledTasks\ListScheduledTasksController;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowProjectController extends Controller
{
    /**
     * The controller that owns each activity category's listing page (see feature-controllers
     * skill) -- `render` below is only responsible for turning the `{category}` path segment
     * into the matching feature controller, never for that feature's own data assembly.
     *
     * @var array<string, class-string>
     */
    private const array ACTIVITY_CATEGORY_CONTROLLERS = [
        'requests'          => ListRequestsController::class,
        'jobs'              => ListJobsController::class,
        'commands'          => ListCommandsController::class,
        'scheduled-tasks'   => ListScheduledTasksController::class,
        'exceptions'        => ListExceptionsController::class,
        'queries'           => ListQueriesController::class,
        'notifications'     => ListNotificationsController::class,
        'mail'              => ListMailController::class,
        'cache'             => ListCacheController::class,
        'outgoing-requests' => ListOutgoingRequestsController::class,
        'metrics'           => ListMetricsController::class,
        'logs'              => ListLogsController::class,
    ];

    public function __construct(
        private readonly OnlineUsersQuery $onlineUsersQuery,
        private readonly ProjectHealthOverviewQuery $projectHealthOverviewQuery,
    ) {}

    public function redirectToDefaultCategory(Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        return to_route('projects.show', [
            'project'  => $project,
            'category' => ObservabilityCategories::default(),
        ]);
    }

    public function render(Request $request, Project $project, string $category): Response
    {
        $this->authorize('view', $project);

        if ($category === 'information') {
            return $this->renderInformation($project);
        }

        if ($category === 'overview') {
            return $this->renderOverview($project);
        }

        abort_unless(ObservabilityCategories::isValid($category), 404);

        if ($category === 'users') {
            return $this->renderUsers($project);
        }

        return app(self::ACTIVITY_CATEGORY_CONTROLLERS[$category])->render($request, $project);
    }

    private function renderInformation(Project $project): Response
    {
        return Inertia::render('projects/information', [
            'project' => new ProjectResource($project),
        ]);
    }

    /**
     * The project's landing summary: every category's 24h volume and error count in one screen, so
     * "is anything broken right now" doesn't require clicking through each tab.
     */
    private function renderOverview(Project $project): Response
    {
        return Inertia::render('projects/overview', [
            'project'     => new ProjectResource($project),
            'categories'  => fn (): array => $this->projectHealthOverviewQuery->execute($project),
            'onlineUsers' => fn (): int => count($this->onlineUsersQuery->execute($project)),
        ]);
    }

    private function renderUsers(Project $project): Response
    {
        return Inertia::render('projects/users', [
            'project'     => new ProjectResource($project),
            'onlineUsers' => fn (): array => $this->onlineUsersQuery->execute($project),
        ]);
    }
}
