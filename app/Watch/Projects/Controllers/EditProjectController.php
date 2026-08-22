<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Actions\UpdateProjectAction;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Requests\UpdateProjectRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EditProjectController extends Controller
{
    public function __construct(
        private readonly UpdateProjectAction $updateProjectAction,
    ) {}

    public function execute(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->updateProjectAction->execute($project, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.show', [
            'project'  => $project,
            'category' => ObservabilityCategories::default(),
        ]);
    }
}
