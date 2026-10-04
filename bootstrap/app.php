<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\PermissionGuard;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RestaurantManagerGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Lati111\LaravelDataproviders\Exceptions\DataproviderException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('data')
                ->group(base_path('routes/dataprovider.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->trimStrings(except: ['current_password', 'password', 'password_confirmation']);

        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.permission' => PermissionGuard::class,
            'guest' => RedirectIfAuthenticated::class,
            'dnd.restaurant.manager' => RestaurantManagerGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Dataprovider exceptions are caused by invalid query parameters, so respond with their client error code
        $exceptions->render(function (DataproviderException $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 400;

            return response()->json([
                'code' => $code,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => null,
            ], $code);
        });
    })->create();
