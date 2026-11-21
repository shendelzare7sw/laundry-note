<?php

use App\Http\Middleware\PastikanAkunAktifApi;
use App\Http\Middleware\PastikanPropertiAktif;
use App\Http\Middleware\PastikanPropertiApi;
use App\Http\Middleware\TambahkanRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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
            'akun.aktif.api' => PastikanAkunAktifApi::class,
        ]);

        $middleware->api(prepend: [
            TambahkanRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'Sesi login Anda telah berakhir atau dicabut oleh pengelola. Silakan masuk kembali.',
                'code' => 'UNAUTHORIZED',
                'request_id' => (string) $request->attributes->get('request_id', ''),
            ], 401);
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $raw = trim((string) $e->getMessage());
            $pesan = ($raw === '' || strcasecmp($raw, 'This action is unauthorized.') === 0)
                ? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'
                : $raw;

            return response()->json([
                'message' => $pesan,
                'code' => 'FORBIDDEN',
                'request_id' => (string) $request->attributes->get('request_id', ''),
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $raw = trim((string) $e->getMessage());
            $isDefaultEn = $raw === '' || str_starts_with($raw, 'No query results for model') || str_starts_with($raw, 'The route ');

            return response()->json([
                'message' => $isDefaultEn ? 'Data yang diminta tidak ditemukan.' : $raw,
                'code' => 'NOT_FOUND',
                'request_id' => (string) $request->attributes->get('request_id', ''),
            ], 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'Terlalu banyak percobaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.',
                'code' => 'TOO_MANY_REQUESTS',
                'request_id' => (string) $request->attributes->get('request_id', ''),
            ], 429);
        });
    })->create();
