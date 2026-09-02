<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers\Requests;

use App\Core\Controllers\Controller;
use App\Watch\Projects\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ListRequestController extends Controller
{
    public function __construct()
    {
    }

    public function render(Request $request, Project $project): void
    {
        $this->authorize('view', $project);

        return Inertia::render('projects/index', [
            'projects' => $project
        ])
    }
}
