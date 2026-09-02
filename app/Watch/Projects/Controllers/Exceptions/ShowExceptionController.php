<?php

declare(strict_types=1);

namespace App\Watch\Projects\Controllers\Exceptions;

use App\Core\Controllers\Controller;
use App\Watch\Ingestion\Queries\ExceptionOccurrencesQuery;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Resources\ProjectResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowExceptionController extends Controller
{
    public function __construct(
        private readonly ExceptionOccurrencesQuery $exceptionOccurrencesQuery,
    )
    {
    }

    public function render(Request $request, Project $project, string $fingerprint): Response
    {
        $this->authorize('view', $project);

        $exception = $this->exceptionOccurrencesQuery->execute($project, $fingerprint);

        throw_if($exception === null, NotFoundHttpException::class);

        return Inertia::render('projects/show/exception', [
            'project' => new ProjectResource($project),
            'fingerprint' => $fingerprint,
            'exception' => $exception,
        ]);
    }
}
