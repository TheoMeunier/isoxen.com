<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Controllers;

use App\Watch\Ingestion\Jobs\StoreOtlpSpans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestTracesController extends IngestOtlpController
{
    /**
     * Accept an OTLP/JSON `ExportTraceServiceRequest` and queue it for storage.
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $this->decode($request, 'resourceSpans');

        StoreOtlpSpans::dispatch($this->project($request)->id, $payload);

        return response()->json([]);
    }
}
