<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers\Logs;

use App\Core\Controllers\Controller;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Queries\ActivityCategoryPageQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListLogsController extends Controller
{
    public function __construct(
        private readonly ActivityCategoryPageQuery $activityCategoryPageQuery,
    ) {}

    public function render(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('projects/logs/index', $this->activityCategoryPageQuery->execute($project, 'logs'));
    }
}
