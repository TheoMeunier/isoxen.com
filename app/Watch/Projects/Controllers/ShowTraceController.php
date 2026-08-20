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
        private readonly TraceLogsQuery $traceLogsQuery,
    ) {}

    /**
     * Render the waterfall for one trace: every span it contains, in the
     * order they started, plus any log lines correlated to it.
     *
     * A trace has no model of its own -- it's just the `trace_id` shared by
     * a group of spans -- so there's nothing to route-model-bind and no
     * separate authorization check beyond the project it belongs to.
     */
    public function render(Request $request, Project $project, string $trace): Response
    {
        $this->authorize('view', $project);

        $spans = $this->traceSpansQuery->execute($project, $trace);

        if ($spans->isEmpty()) {
            // Either the trace id is wrong, or it belongs to a different
            // project than the one in the URL -- both cases should look
            // like "not found", not leak which one it was.
            throw new NotFoundHttpException;
        }

        return Inertia::render('projects/trace', [
            'project' => new ProjectResource($project),
            'traceId' => $trace,
            'spans'   => $spans->values(),
            'logs'    => $this->traceLogsQuery->execute($project, $trace)->values(),
        ]);
    }
}
