<?php

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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // --- AÑADE ESTA SECCIÓN PARA REGISTRAR EL ALIAS DEL MIDDLEWARE ---
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        // --- FIN DE LA SECCIÓN AÑADIDA ---

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
