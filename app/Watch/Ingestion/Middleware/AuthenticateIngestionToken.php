<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Middleware;

use App\Watch\Projects\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {@see Project} from the bearer ingestion token into the `otelProject` request attribute.
 */
class AuthenticateIngestionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        abort_if($token === null, 401, 'Missing ingestion token.');

        $project = Project::query()->where('token', $token)->first();

        abort_if($project === null, 401, 'Invalid ingestion token.');

        $request->attributes->set('otelProject', $project);

        return $next($request);
    }
}
