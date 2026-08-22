<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Controllers;

use App\Core\Controllers\Controller;
use App\Watch\Projects\Models\Project;
use Illuminate\Http\Request;

/**
 * Shared request decoding and project resolution for the three OTLP/HTTP+JSON ingestion endpoints.
 */
abstract class IngestOtlpController extends Controller
{
    /**
     * Decode the request body as OTLP/JSON; a missing root key is a valid empty export, not an error.
     *
     * @return array<string, mixed>
     */
    protected function decode(Request $request, string $rootKey): array
    {
        abort_unless($request->isJson(), 415, 'Only OTLP/HTTP+JSON is supported for now; protobuf support is planned separately.');

        $payload = $request->json()->all();

        if (! array_key_exists($rootKey, $payload)) {
            return [$rootKey => []];
        }

        abort_unless(is_array($payload[$rootKey]), 400, "Invalid \"{$rootKey}\" in payload: expected a list, got ".get_debug_type($payload[$rootKey]).'.');

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
