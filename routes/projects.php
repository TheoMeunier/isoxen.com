<?php

use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Controllers\CreateProjectController;
use App\Watch\Projects\Controllers\DeleteProjectController;
use App\Watch\Projects\Controllers\EditProjectController;
use App\Watch\Projects\Controllers\ListProjectsController;
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
    // slug 404s here before it ever reaches the controller.
    Route::get('projects/{project}/{category}', [ShowProjectController::class, 'render'])
        ->where('category', implode('|', [...array_keys(ObservabilityCategories::all()), 'information']))
        ->name('projects.show');

    Route::put('projects/{project}', [EditProjectController::class, 'execute'])->name('projects.update');
    Route::delete('projects/{project}', [DeleteProjectController::class, 'execute'])->name('projects.destroy');

    // Trace ids are 32 lowercase hex characters (OTEL_TRACE_ID), so the
    // constraint doubles as a quick 404 for obviously-wrong URLs.
    Route::get('projects/{project}/traces/{trace}', [ShowTraceController::class, 'render'])
        ->where('trace', '[0-9a-f]{32}')
        ->name('projects.traces.show');
});
