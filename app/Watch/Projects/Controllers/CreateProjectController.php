<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Projects\Actions\CreateProjectAction;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Requests\CreateProjectRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CreateProjectController extends Controller
{
    public function __construct(
        private readonly CreateProjectAction $createProjectAction,
    ) {}

    /**
     * Create a new project for the authenticated user.
     */
    public function execute(CreateProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->createProjectAction->execute($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', $project);
    }
}
