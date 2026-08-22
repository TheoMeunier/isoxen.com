<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Controllers;

use App\Watch\Ingestion\Jobs\StoreOtlpMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestMetricsController extends IngestOtlpController
{
    public function store(Request $request): JsonResponse
    {
        $payload = $this->decode($request, 'resourceMetrics');

        if (! $this->isEmpty($payload, 'resourceMetrics')) {
            dispatch(new \App\Watch\Ingestion\Jobs\StoreOtlpMetrics($this->project($request)->id, $payload));
        }

        return response()->json([]);
    }
}
