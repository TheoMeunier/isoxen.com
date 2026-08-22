<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Middleware;

use App\Watch\Projects\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Resolves the {@see Project} from the bearer ingestion token into the `otelProject` request attribute. */
class AuthenticateIngestionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            abort(401, 'Missing ingestion token.');
        }

        $project = Project::query()->where('token', $token)->first();

        if ($project === null) {
            abort(401, 'Invalid ingestion token.');
        }

        $request->attributes->set('otelProject', $project);

        return $next($request);
    }
}
