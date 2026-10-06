<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Remove stateful middleware for API routes since we're using Bearer tokens
        // $middleware->api(prepend: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);

        // Disable CSRF for API routes for external frontend
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->alias([
            'superadmin' => \App\Http\Middleware\IsSuperAdmin::class,
            'shopowner'  => \App\Http\Middleware\IsShopOwner::class,
            'can.perm'   => \App\Http\Middleware\RequirePermission::class,
            'active'     => \App\Http\Middleware\EnsureActiveAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\InsufficientStockException $e, $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
        $exceptions->render(function (\App\Exceptions\PriceBelowMinimumException|\App\Exceptions\MinimumMarginViolationException $e, $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
    })
    ->create();
