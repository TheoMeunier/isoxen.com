<?php

declare(strict_types=1);

use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Controllers\Exceptions\ShowExceptionController;
use App\Watch\Projects\Controllers\Projects\CreateProjectController;
use App\Watch\Projects\Controllers\Projects\DeleteProjectController;
use App\Watch\Projects\Controllers\Projects\EditProjectController;
use App\Watch\Projects\Controllers\Projects\ListProjectsController;
use App\Watch\Projects\Controllers\Query\ShowQueryController;
use App\Watch\Projects\Controllers\Requests\ShowRequestOccurrenceController;
use App\Watch\Projects\Controllers\Requests\ShowRouteController;
use App\Watch\Projects\Controllers\ShowProjectController;
use App\Watch\Projects\Controllers\ShowTraceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('projects', [ListProjectsController::class, 'render'])->name('projects.index');
    Route::post('projects', [CreateProjectController::class, 'execute'])->name('projects.store');

    // A bare project URL -- an old bookmark, or a link built before
    // categories moved into the path -- redirects to the project's default
    // category instead of 404ing.
    Route::get('projects/{project}', [ShowProjectController::class, 'redirectToDefaultCategory'])
        ->name('projects.show-redirect');

    // The category is a path segment, not a query string, so an unknown
    // slug 404s here before it ever reaches the controller. 'information'
    // and 'overview' are pseudo-categories -- project settings and the
    // cross-category health summary -- neither backed by an entry in
    // ObservabilityCategories, so they're added on top of it here.
    Route::get('projects/{project}/{category}', [ShowProjectController::class, 'render'])
        ->where('category', implode('|', [...array_keys(ObservabilityCategories::all()), 'information', 'overview']))
        ->name('projects.show');

    Route::put('projects/{project}', [EditProjectController::class, 'execute'])->name('projects.update');
    Route::delete('projects/{project}', [DeleteProjectController::class, 'execute'])->name('projects.destroy');

    // Trace ids are 32 lowercase hex characters (OTEL_TRACE_ID), so the
    // constraint doubles as a quick 404 for obviously-wrong URLs.
    Route::get('projects/{project}/traces/{trace}', [ShowTraceController::class, 'render'])
        ->where('trace', '[0-9a-f]{32}')
        ->name('projects.traces.show');

    // Fingerprints are sha256 hex (see ExceptionFingerprint), so the
    // constraint doubles as a quick 404 for obviously-wrong URLs.
    Route::get('projects/{project}/exceptions/{fingerprint}', [ShowExceptionController::class, 'render'])
        ->where('fingerprint', '[0-9a-f]{64}')
        ->name('projects.exceptions.show');

    // Fingerprints are sha256 hex (see QueryFingerprint), same shape as the
    // exceptions route above. Also serves the Queries table's drawer as
    // JSON -- see ShowQueryController.
    Route::get('projects/{project}/queries/{fingerprint}', [ShowQueryController::class, 'render'])
        ->where('fingerprint', '[0-9a-f]{64}')
        ->name('projects.queries.show');

    // One specific request out of a route's "Recent requests" list,
    // addressed by trace id + span id (see ShowRequestOccurrenceController
    // for why both are needed). Trace ids are 32 hex characters, span ids
    // 16 -- same OTEL id shapes the trace route above already constrains.
    Route::get('projects/{project}/requests/trace/{trace}/{span}', [ShowRequestOccurrenceController::class, 'render'])
        ->where(['trace' => '[0-9a-f]{32}', 'span' => '[0-9a-f]{16}'])
        ->name('projects.requests.occurrence');

    // Fingerprints are sha256 hex (see RouteFingerprint), same shape as the
    // exceptions/queries routes above. Also serves the Routes table's
    // drawer as JSON -- see ShowRouteController.
    Route::get('projects/{project}/requests/{fingerprint}', [ShowRouteController::class, 'render'])
        ->where('fingerprint', '[0-9a-f]{64}')
        ->name('projects.requests.show');
});
