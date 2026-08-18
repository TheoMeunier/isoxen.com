<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Projects\Actions\DeleteProjectAction;
use App\Watch\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DeleteProjectController extends Controller
{
    public function __construct(
        private readonly DeleteProjectAction $deleteProjectAction,
    ) {}

    /**
     * Delete the given project.
     */
    public function execute(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->deleteProjectAction->execute($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }
}
