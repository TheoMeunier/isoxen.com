<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Controllers;

use App\Watch\Ingestion\Jobs\StoreOtlpLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestLogsController extends IngestOtlpController
{
    public function store(Request $request): JsonResponse
    {
        $payload = $this->decode($request, 'resourceLogs');

        if (! $this->isEmpty($payload, 'resourceLogs')) {
            dispatch(new \App\Watch\Ingestion\Jobs\StoreOtlpLogs($this->project($request)->id, $payload));
        }

        return response()->json([]);
    }
}
