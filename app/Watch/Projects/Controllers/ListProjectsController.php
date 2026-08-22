<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListProjectsController extends Controller
{
    public function render(Request $request): Response
    {
        return Inertia::render('projects/index', [
            'projects' => ProjectResource::collection(
                $request->user()->projects()->latest()->get(),
            ),
        ]);
    }
}
