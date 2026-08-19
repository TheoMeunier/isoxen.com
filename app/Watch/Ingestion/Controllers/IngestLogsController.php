<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Controllers;

use App\Watch\Ingestion\Jobs\StoreOtlpLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestLogsController extends IngestOtlpController
{
    /**
     * Accept an OTLP/JSON `ExportLogsServiceRequest` and queue it for storage.
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $this->decode($request, 'resourceLogs');

        StoreOtlpLogs::dispatch($this->project($request)->id, $payload);

        return response()->json([]);
    }
}
