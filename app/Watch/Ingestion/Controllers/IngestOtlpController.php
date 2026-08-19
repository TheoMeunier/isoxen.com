<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Projects\Models\Project;
use Illuminate\Http\Request;

/**
 * Shared behaviour for the three OTLP/HTTP ingestion endpoints
 * (traces/metrics/logs): validating the request shape and resolving the
 * authenticated project.
 *
 * Only OTLP/HTTP+JSON is supported for now (see ADR-0001). Protobuf support
 * is a separate, later task.
 */
abstract class IngestOtlpController extends Controller
{
    /**
     * Decode the request body as an OTLP/JSON payload, aborting with a
     * clear error when the request isn't JSON or is missing the expected
     * top-level key.
     *
     * @return array<string, mixed>
     */
    protected function decode(Request $request, string $rootKey): array
    {
        if (! $request->isJson()) {
            abort(415, 'Only OTLP/HTTP+JSON is supported for now; protobuf support is planned separately.');
        }

        $payload = $request->json()->all();

        if (! is_array($payload[$rootKey] ?? null)) {
            abort(400, "Missing or invalid \"{$rootKey}\" in payload.");
        }

        return $payload;
    }

    protected function project(Request $request): Project
    {
        /** @var Project $project */
        $project = $request->attributes->get('otelProject');

        return $project;
    }
}
