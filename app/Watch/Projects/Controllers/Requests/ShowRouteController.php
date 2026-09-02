<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers\Requests;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\RouteOccurrencesQuery;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowRouteController extends Controller
{
    public function __construct(
        private readonly RouteOccurrencesQuery $routeOccurrencesQuery,
    )
    {
    }
    
    public function render(Request $request, Project $project, string $fingerprint): Response|JsonResponse
    {
        $this->authorize('view', $project);

        $route = $this->routeOccurrencesQuery->execute($project, $fingerprint);

        throw_if($route === null, NotFoundHttpException::class);

        if ($request->wantsJson()) {
            return response()->json($route);
        }

        return Inertia::render('projects/show/route', [
            'project' => new ProjectResource($project),
            'route' => $route,
        ]);
    }
}
