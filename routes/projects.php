<?php

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
    Route::get('projects/{project}', [ShowProjectController::class, 'render'])->name('projects.show');
    Route::put('projects/{project}', [EditProjectController::class, 'execute'])->name('projects.update');
    Route::delete('projects/{project}', [DeleteProjectController::class, 'execute'])->name('projects.destroy');

    // Trace ids are 32 lowercase hex characters (OTEL_TRACE_ID), so the
    // constraint doubles as a quick 404 for obviously-wrong URLs.
    Route::get('projects/{project}/traces/{trace}', [ShowTraceController::class, 'render'])
        ->where('trace', '[0-9a-f]{32}')
        ->name('projects.traces.show');
});
