<?php

declare(strict_types=1);

namespace App\Watch\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Resources\ProjectResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListProjectsController extends Controller
{
    /**
     * Render the authenticated user's list of projects.
     */
    public function render(Request $request): Response
    {
        $projects = $request->user()->projects()->latest()->get();
        return Inertia::render('projects/index', [
            'projects' => ProjectResource::collection($projects),
        ]);
    }
}
