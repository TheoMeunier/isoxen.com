<?php

use App\Watch\Ingestion\Controllers\IngestLogsController;
use App\Watch\Ingestion\Controllers\IngestMetricsController;
use App\Watch\Ingestion\Controllers\IngestTracesController;
use Illuminate\Support\Facades\Route;

// OTLP/HTTP ingestion endpoints, following the standard paths so any OTEL
// SDK configured with OTEL_EXPORTER_OTLP_ENDPOINT pointing at this app's
// root works without extra configuration.
//
// These routes intentionally use the `api` middleware group (stateless, no
// CSRF, no session cookie) instead of `web`, since callers are other
// applications' OTEL SDKs, not browsers. See ADR-0001.
Route::middleware(['api', 'otel.auth'])->group(function (): void {
    Route::post('v1/traces', [IngestTracesController::class, 'store'])->name('otel.traces');
    Route::post('v1/metrics', [IngestMetricsController::class, 'store'])->name('otel.metrics');
    Route::post('v1/logs', [IngestLogsController::class, 'store'])->name('otel.logs');
});
