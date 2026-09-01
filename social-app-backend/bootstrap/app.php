<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The web stack is unused for an API-only backend. Keep the default
        // middleware registered so the console still works, but do not append
        // any session/cookie specific middleware to API requests.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Treat every request as JSON so API consumers always receive JSON
        // error payloads (validation, 404, 500, auth failures, etc.).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => true,
        );
    })->create();
