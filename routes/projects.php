<?php

use App\Watch\Projects\Controllers\CreateProjectController;
use App\Watch\Projects\Controllers\DeleteProjectController;
use App\Watch\Projects\Controllers\EditProjectController;
use App\Watch\Projects\Controllers\ListProjectsController;
use App\Watch\Projects\Controllers\ShowProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('projects', [ListProjectsController::class, 'render'])->name('projects.index');
    Route::post('projects', [CreateProjectController::class, 'execute'])->name('projects.store');
    Route::get('projects/{project}', [ShowProjectController::class, 'render'])->name('projects.show');
    Route::put('projects/{project}', [EditProjectController::class, 'execute'])->name('projects.update');
    Route::delete('projects/{project}', [DeleteProjectController::class, 'execute'])->name('projects.destroy');
});
