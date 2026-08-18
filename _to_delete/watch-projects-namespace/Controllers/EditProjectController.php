<?php

declare(strict_types=1);

namespace App\Watch\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Actions\UpdateProjectAction;
use App\Watch\Models\Project;
use App\Watch\Requests\UpdateProjectRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EditProjectController extends Controller
{
    public function __construct(
        private readonly UpdateProjectAction $updateProjectAction,
    ) {}

    /**
     * Update the given project.
     */
    public function execute(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->updateProjectAction->execute($project, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.show', $project);
    }
}
