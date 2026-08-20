<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/projects.php';

// routes/otel.php is deliberately not required here: it's registered in
// bootstrap/app.php so it lands in the stateless `api` middleware group.
