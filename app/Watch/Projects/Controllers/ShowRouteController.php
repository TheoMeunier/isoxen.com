<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

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
    ) {}

    /**
     * Serves both the full detail page and the Routes table's drawer from
     * one route: the drawer fetches this same URL with `Accept:
     * application/json` (see RoutesTable) to preview a route without
     * navigating away, so there's no second endpoint to keep in sync with
     * the page -- mirrors ShowQueryController.
     */
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
            'route'   => $route,
        ]);
    }
}
