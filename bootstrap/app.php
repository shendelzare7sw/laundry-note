<?php

use App\Http\Middleware\PastikanPropertiAktif;
use App\Http\Middleware\PastikanPropertiApi;
use App\Http\Middleware\TambahkanRequestId;
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
        $middleware->alias([
            'properti.aktif' => PastikanPropertiAktif::class,
            'properti.api' => PastikanPropertiApi::class,
        ]);

        $middleware->api(prepend: [
            TambahkanRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
