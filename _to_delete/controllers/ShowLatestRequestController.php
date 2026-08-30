<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\RequestOccurrenceQuery;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Inertia\Inertia;
use Inertia\Response;

class ShowLatestRequestController extends Controller
{
    public function __construct(
        private readonly RequestOccurrenceQuery $requestOccurrenceQuery,
    ) {}

    /**
     * The sidebar's "Latest request" shortcut -- jumps straight to the
     * Info/Details breakdown (see RequestActivityPanel) for the newest
     * request the project has received, without going through a specific
     * route's aggregate page first. `request` is null for a project that
     * hasn't received one yet; the page renders an empty state for that.
     */
    public function render(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('projects/show/request-occurrence', [
            'project' => new ProjectResource($project),
            'request' => $this->requestOccurrenceQuery->latest($project),
        ]);
    }
}
