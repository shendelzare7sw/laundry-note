<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TambahkanRequestId
{
    /**
     * Sematkan X-Request-Id pada setiap request & response API untuk pelacakan galat klien mobile.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->header('X-Request-Id', ''));

        if ($requestId === '' || strlen($requestId) > 64) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
