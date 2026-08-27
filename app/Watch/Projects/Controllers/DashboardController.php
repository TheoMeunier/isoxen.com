<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\LatestExceptionQuery;
use App\Watch\Ingestion\Queries\OnlineUsersQuery;
use App\Watch\Ingestion\Queries\ProjectUptimeQuery;
use App\Watch\Ingestion\Queries\RequestStatusBreakdownQuery;
use App\Watch\Ingestion\Queries\SlowestEndpointQuery;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RequestStatusBreakdownQuery $requestStatusBreakdownQuery,
        private readonly OnlineUsersQuery $onlineUsersQuery,
        private readonly SlowestEndpointQuery $slowestEndpointQuery,
        private readonly LatestExceptionQuery $latestExceptionQuery,
        private readonly ProjectUptimeQuery $projectUptimeQuery,
    ) {}

    /**
     * The landing page after login: one card per project with its request volume, error split, uptime
     * and a small summary (slowest endpoint, latest exception) -- everything about a project lives on
     * its own card rather than being split into separate fleet-wide sections elsewhere on the page. A
     * placeholder page when there's nothing to summarize yet.
     */
    public function render(Request $request): Response
    {
        /** @var Collection<int, Project> $projects */
        $projects = $request->user()->projects()->latest()->get();

        if ($projects->isEmpty()) {
            return Inertia::render('dashboard', [
                'projects'     => [],
                'overview'     => null,
                'projectStats' => [],
            ]);
        }

        $projectStats = $this->projectStats($projects);

        return Inertia::render('dashboard', [
            'projects'     => ProjectResource::collection($this->byUrgency($projects, $projectStats)),
            'overview'     => $this->overview($projects, $projectStats),
            'projectStats' => $projectStats,
        ]);
    }

    /**
     * The project cards, worst-first: whoever has server errors right now belongs at the top of the
     * page, not wherever their creation date happens to place them. PHP's stable sort keeps the
     * original latest-first order as the tiebreaker among projects with the same error count.
     *
     * @param  Collection<int, Project>  $projects
     * @param  array<int, array{projectId: int, serverErrors: int}>  $projectStats
     * @return Collection<int, Project>
     */
    private function byUrgency(Collection $projects, array $projectStats): Collection
    {
        $serverErrorsByProject = array_column($projectStats, 'serverErrors', 'projectId');

        return $projects->sortByDesc(
            fn (Project $project): int => $serverErrorsByProject[$project->id] ?? 0,
        )->values();
    }

    /**
     * One entry per project -- request volume, error split, live user count, an hour-by-hour
     * 1XX-3XX/4XX/5XX split, a simple "is telemetry still arriving" uptime signal (see
     * {@see ProjectUptimeQuery}), and that project's own slowest endpoint and latest exception. Backs
     * both the project cards -- which is the *only* place this data appears now, deliberately, rather
     * than being duplicated into a separate fleet-wide section -- and (summed below) the overview row.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{projectId: int, requests: int, clientErrors: int, serverErrors: int, onlineUsers: int, statusTimeline: array<int, array{at: string, success: int, client_error: int, server_error: int}>, slowestEndpoint: array{name: string, avgMs: float, total: int}|null, latestException: array{time: string, name: string|null, statusMessage: string|null, traceId: string|null}|null, online: bool, lastSeenAt: string|null}>
     */
    private function projectStats(Collection $projects): array
    {
        $stats = [];

        foreach ($projects as $project) {
            $breakdown = $this->requestStatusBreakdownQuery->execute($project);
            $uptime    = $this->projectUptimeQuery->execute($project);

            $stats[] = [
                'projectId'       => $project->id,
                'requests'        => $breakdown['success'] + $breakdown['client_error'] + $breakdown['server_error'],
                'clientErrors'    => $breakdown['client_error'],
                'serverErrors'    => $breakdown['server_error'],
                'onlineUsers'     => count($this->onlineUsersQuery->execute($project)),
                'statusTimeline'  => $this->requestStatusBreakdownQuery->executeTimeline($project),
                'slowestEndpoint' => $this->slowestEndpointQuery->execute($project),
                'latestException' => $this->latestExceptionQuery->execute($project),
                'online'          => $uptime['online'],
                'lastSeenAt'      => $uptime['lastSeenAt'],
            ];
        }

        return $stats;
    }

    /**
     * The KPI row at the top of the dashboard: every project's numbers added together, plus how many
     * are currently online.
     *
     * @param  Collection<int, Project>  $projects
     * @param  array<int, array{requests: int, clientErrors: int, serverErrors: int, onlineUsers: int, online: bool}>  $projectStats
     * @return array{projectsCount: int, projectsOnline: int, requests24h: int, clientErrors24h: int, serverErrors24h: int, onlineUsers: int}
     */
    private function overview(Collection $projects, array $projectStats): array
    {
        return [
            'projectsCount'   => $projects->count(),
            'projectsOnline'  => count(array_filter($projectStats, fn (array $stat): bool => $stat['online'])),
            'requests24h'     => array_sum(array_column($projectStats, 'requests')),
            'clientErrors24h' => array_sum(array_column($projectStats, 'clientErrors')),
            'serverErrors24h' => array_sum(array_column($projectStats, 'serverErrors')),
            'onlineUsers'     => array_sum(array_column($projectStats, 'onlineUsers')),
        ];
    }
}
