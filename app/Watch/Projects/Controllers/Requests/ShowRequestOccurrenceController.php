<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers\Requests;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\RequestOccurrenceQuery;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowRequestOccurrenceController extends Controller
{
    public function __construct(
        private readonly RequestOccurrenceQuery $requestOccurrenceQuery,
    )
    {
    }

    public function render(Project $project, string $trace, string $span): Response
    {
        $this->authorize('view', $project);

        $request = $this->requestOccurrenceQuery->find($project, $trace, $span);

        throw_if($request === null, NotFoundHttpException::class);

        return Inertia::render('projects/requests/occurrence', [
            'project' => new ProjectResource($project),
            'request' => $request,
        ]);
    }
}
