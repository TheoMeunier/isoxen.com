<?php

declare(strict_types=1);

use App\Core\Middleware\HandleAppearance;
use App\Core\Middleware\HandleInertiaRequests;
use App\Watch\Ingestion\Middleware\AuthenticateIngestionToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The OTLP ingestion routes are registered here rather than from
        // within routes/web.php so they get the stateless `api` group
        // instead of `web`: their callers are other applications' OTEL
        // SDKs, which have no session and no CSRF token.
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/otel.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'otel.auth' => AuthenticateIngestionToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
