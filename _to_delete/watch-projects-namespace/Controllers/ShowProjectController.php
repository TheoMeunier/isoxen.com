<?php

declare(strict_types=1);

namespace App\Watch\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Models\Project;
use App\Watch\Resources\ProjectResource;
use Inertia\Inertia;
use Inertia\Response;

class ShowProjectController extends Controller
{
    /**
     * Render the given project, including its ingestion token.
     */
    public function render(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('projects/show', [
            'project' => new ProjectResource($project),
        ]);
    }
}
