<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\TraceLogsQuery;
use App\Watch\Ingestion\Queries\TraceSpansQuery;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowTraceController extends Controller
{
    public function __construct(
        private readonly TraceSpansQuery $traceSpansQuery,
        private readonly TraceLogsQuery  $traceLogsQuery,
    )
    {
    }

    public function render(Request $request, Project $project, string $trace): Response
    {
        $this->authorize('view', $project);

        $spans = $this->traceSpansQuery->execute($project, $trace);

        if ($spans->isEmpty()) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('projects/trace', [
            'project' => new ProjectResource($project),
            'traceId' => $trace,
            'spans' => $spans->values(),
            'logs' => $this->traceLogsQuery->execute($project, $trace)->values(),
        ]);
    }
}
