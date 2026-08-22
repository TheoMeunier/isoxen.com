<?php

declare(strict_types=1);

use App\Watch\Ingestion\Controllers\IngestLogsController;
use App\Watch\Ingestion\Controllers\IngestMetricsController;
use App\Watch\Ingestion\Controllers\IngestTracesController;
use Illuminate\Support\Facades\Route;

// OTLP/HTTP ingestion endpoints, following the standard paths so any OTEL
// SDK configured with OTEL_EXPORTER_OTLP_ENDPOINT pointing at this app's
// root works without extra configuration.
//
// The stateless `api` middleware group is applied where this file is
// registered, in bootstrap/app.php -- these callers are other applications'
// OTEL SDKs, not browsers, so they have no session and no CSRF token.
Route::middleware('otel.auth')->group(function (): void {
    Route::post('v1/traces', [IngestTracesController::class, 'store'])->name('otel.traces');
    Route::post('v1/metrics', [IngestMetricsController::class, 'store'])->name('otel.metrics');
    Route::post('v1/logs', [IngestLogsController::class, 'store'])->name('otel.logs');
});
