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
     * Decode the request body as an OTLP/JSON payload.
     *
     * An export carrying no data is *valid* OTLP and must be accepted, not
     * rejected. This matters more than it sounds: protobuf's JSON mapping
     * omits empty repeated fields entirely, so an exporter with nothing to
     * report sends `{}` — no `resourceSpans` key at all. Treating that as a
     * 400 makes the sender retry, give up, and log an error for what is
     * simply a quiet minute, which reads as a broken pipeline.
     *
     * A body that isn't JSON, or whose root key holds something other than
     * a list, is still rejected — that's a real client error.
     *
     * @return array<string, mixed>
     */
    protected function decode(Request $request, string $rootKey): array
    {
        if (! $request->isJson()) {
            abort(415, 'Only OTLP/HTTP+JSON is supported for now; protobuf support is planned separately.');
        }

        $payload = $request->json()->all();

        if (! array_key_exists($rootKey, $payload)) {
            return [$rootKey => []];
        }

        if (! is_array($payload[$rootKey])) {
            abort(400, "Invalid \"{$rootKey}\" in payload: expected a list, got ".get_debug_type($payload[$rootKey]).'.');
        }

        return $payload;
    }

    /**
     * Whether this export actually carries anything worth queueing.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function isEmpty(array $payload, string $rootKey): bool
    {
        return ($payload[$rootKey] ?? []) === [];
    }

    protected function project(Request $request): Project
    {
        /** @var Project $project */
        $project = $request->attributes->get('otelProject');

        return $project;
    }
}
