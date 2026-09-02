<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers\Query;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\QueryOccurrencesQuery;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowQueryController extends Controller
{
    public function __construct(
        private readonly QueryOccurrencesQuery $queryOccurrencesQuery,
    )
    {
    }
    
    public function render(Request $request, Project $project, string $fingerprint): Response|JsonResponse
    {
        $this->authorize('view', $project);

        $query = $this->queryOccurrencesQuery->execute($project, $fingerprint);

        throw_if($query === null, NotFoundHttpException::class);

        if ($request->wantsJson()) {
            return response()->json($query);
        }

        return Inertia::render('projects/show/query', [
            'project' => new ProjectResource($project),
            'query' => $query,
        ]);
    }
}
