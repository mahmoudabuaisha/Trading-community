<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA auth: same-origin requests to /api use the session cookie + CSRF.
        $middleware->statefulApi();
        $middleware->api(append: [SetLocaleFromRequest::class]);
        $middleware->alias(['role' => EnsureRole::class]);
        // An already signed-in SPA hitting a guest-only endpoint gets JSON, not a redirect.
        $middleware->redirectUsersTo(fn (Request $request) => abort(409, 'Already authenticated.'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
