<?php

use App\Http\Middleware\AuthenticateFromCookie;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Bare-domain root: short orientation payload (not under /api/v1).
            Route::get('/', fn () => response()->json([
                'name' => config('app.name'),
                'version' => env('API_VERSION', '1.0.0'),
                'docs' => '/docs/api',
                'api' => '/api/v1',
                'time' => now()->toIso8601String(),
            ]))->name('root');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reads the httpOnly `accessToken` cookie into a Bearer header so the
        // JWT guard can authenticate the request without any custom guard code.
        $middleware->api(prepend: [
            AuthenticateFromCookie::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Treat every request as JSON so API consumers always receive JSON
        // error payloads (validation, 404, 500, auth failures, etc.).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => true,
        );

        // Render exceptions in the NestJS-compatible shape the Sakai client
        // expects: { statusCode, message, error }. Validation errors carry
        // `message` as a string[] of per-field failures. Applied to every
        // request — this is an API-only server, so even a hit on `/` should
        // fail with JSON, not Laravel's debug HTML.
        $exceptions->render(function (Throwable $e, Request $request) {
            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $message = $e instanceof ValidationException
                ? collect($e->errors())->flatten()->all()
                : ($e->getMessage() ?: Response::$statusTexts[$status] ?? 'Error');

            $payload = [
                'statusCode' => $status,
                'message' => $message,
            ];

            if ($status >= 400 && $status < 500 && $status !== 422 && $status !== 401) {
                $payload['error'] = Response::$statusTexts[$status] ?? 'Error';
            }

            return response()->json($payload, $status);
        });
    })->create();
