<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

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
    ) {}

    /**
     * One specific request out of a route's own "Recent requests" list --
     * clicking a row there lands here instead of expanding the row in
     * place (see RequestActivityPanel), addressed by the pair that
     * uniquely identifies a request span: its trace id and its own span id
     * within that trace (a trace id alone isn't enough -- outgoing
     * requests and queued jobs the request triggered can carry the same
     * trace id under a different span).
     */
    public function render(Project $project, string $trace, string $span): Response
    {
        $this->authorize('view', $project);

        $request = $this->requestOccurrenceQuery->find($project, $trace, $span);

        throw_if($request === null, NotFoundHttpException::class);

        return Inertia::render('projects/show/request-occurrence', [
            'project' => new ProjectResource($project),
            'request' => $request,
        ]);
    }
}
