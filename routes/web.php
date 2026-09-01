<?php

use App\Watch\Projects\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'render'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/projects.php';

// Temporary: visit this once to trigger a real exception with an HTTP
// status code, so we can confirm the Exceptions page renders the type,
// message, and HTTP status badge correctly end to end. Uses a failed
// outgoing HTTP call rather than abort() -- Laravel's default exception
// handler never reports HttpException (abort()'s 4xx/5xx are treated as
// expected responses, not errors), but a RequestException from a failed
// HTTP client call is reported normally. Safe to remove once confirmed.
Route::get('debug/test-exception', function (): void {
    Illuminate\Support\Facades\Http::fake([
        '*' => Illuminate\Support\Facades\Http::response(['error' => 'Simulated failure'], 503),
    ]);

    Illuminate\Support\Facades\Http::get('https://example.test/simulated')->throw();
})->name('debug.test-exception');

// routes/otel.php is deliberately not required here: it's registered in
// bootstrap/app.php so it lands in the stateless `api` middleware group.
