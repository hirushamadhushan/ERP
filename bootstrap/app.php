<?php

use App\Support\ErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token', '_token', 'two_factor_secret', 'two_factor_recovery_codes']);
        $exceptions->context(function () {
            return app()->bound('request') && ! app()->runningInConsole()
                ? ['error_reference' => ErrorResponse::reference(request())]
                : [];
        });
        $exceptions->respond(fn ($response, $exception, $request) => ErrorResponse::render($response, $exception, $request));
    })->create();
