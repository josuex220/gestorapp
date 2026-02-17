<?php

// config/auth.php — Add these to the existing guards and providers arrays

/*
|--------------------------------------------------------------------------
| Admin Guard Configuration
|--------------------------------------------------------------------------
| Add these entries to your config/auth.php file.
|
*/

// In 'guards' array, add:
// 'admin' => [
//     'driver' => 'sanctum',
//     'provider' => 'admins',
// ],

// In 'providers' array, add:
// 'admins' => [
//     'driver' => 'eloquent',
//     'model' => App\Models\Admin::class,
// ],


/*
|--------------------------------------------------------------------------
| Sanctum Configuration
|--------------------------------------------------------------------------
| In config/sanctum.php, make sure the 'guard' includes 'admin':
| 'guard' => ['web', 'admin'],
|
*/


/*
|--------------------------------------------------------------------------
| Kernel / bootstrap/app.php — Register Middleware Alias
|--------------------------------------------------------------------------
| Add the 'is_admin' alias:
|
| In app/Http/Kernel.php (Laravel 10):
| protected $middlewareAliases = [
|     ...
|     'is_admin' => \App\Http\Middleware\EnsureIsAdmin::class,
| ];
|
| In bootstrap/app.php (Laravel 11+):
| ->withMiddleware(function (Middleware $middleware) {
|     $middleware->alias([
|         'is_admin' => \App\Http\Middleware\EnsureIsAdmin::class,
|     ]);
| })
|
*/


/*
|--------------------------------------------------------------------------
| RouteServiceProvider or bootstrap/app.php — Register Admin Routes
|--------------------------------------------------------------------------
|
| In app/Providers/RouteServiceProvider.php:
| Route::middleware('api')
|     ->prefix('api/admin')
|     ->group(base_path('routes/api_admin.php'));
|
| In bootstrap/app.php (Laravel 11+):
| ->withRouting(
|     api: __DIR__.'/../routes/api.php',
|     commands: __DIR__.'/../routes/console.php',
|     health: '/up',
|     then: function () {
|         Route::middleware('api')
|             ->prefix('api/admin')
|             ->group(base_path('routes/api_admin.php'));
|     },
| )
|
*/
