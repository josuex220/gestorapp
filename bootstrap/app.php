<?php

use App\Http\Middleware\CheckClientLimit;
use App\Http\Middleware\UpdateSessionActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function ($router) {
            Route::middleware('api')
                ->prefix('api/admin')
                ->group(base_path('routes/api_admin.php'));
        },

    )
    ->withProviders([
        \SocialiteProviders\Manager\ServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(UpdateSessionActivity::class);
        $middleware->append(CheckClientLimit::class);
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/mercadopago',
        ]);
        $middleware->alias([
            'is_admin' => \App\Http\Middleware\EnsureIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
       $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors'  => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        });
    })->create();
