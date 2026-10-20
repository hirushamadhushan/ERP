<?php

use App\Support\ErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Composer cannot refresh generated mappings in some restricted Windows runtimes.
// Register the optional PDF package paths defensively; normal Composer installs
// already contain these mappings, and addPsr4 simply keeps them idempotent.
if (is_dir(__DIR__.'/../vendor/barryvdh/laravel-dompdf')) {
    $loaders = \Composer\Autoload\ClassLoader::getRegisteredLoaders();
    $loader = reset($loaders);
    if ($loader) {
        $loader->addPsr4('Barryvdh\\DomPDF\\', __DIR__.'/../vendor/barryvdh/laravel-dompdf/src');
        $loader->addPsr4('Dompdf\\', __DIR__.'/../vendor/dompdf/dompdf/src');
        $loader->addPsr4('Dompdf\\', __DIR__.'/../vendor/dompdf/dompdf/lib', true);
        $loader->addPsr4('FontLib\\', __DIR__.'/../vendor/dompdf/php-font-lib/src/FontLib');
        $loader->addPsr4('Svg\\', __DIR__.'/../vendor/dompdf/php-svg-lib/src/Svg');
        $loader->addPsr4('Masterminds\\', __DIR__.'/../vendor/masterminds/html5/src');
        $loader->addPsr4('Sabberworm\\CSS\\', __DIR__.'/../vendor/sabberworm/php-css-parser/src');
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [\App\Http\Middleware\AsyncFormResponse::class]);
        $middleware->alias(['role.permission' => \App\Http\Middleware\RequireRolePermission::class]);
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
