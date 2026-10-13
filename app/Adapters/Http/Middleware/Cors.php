<?php

declare(strict_types=1);

namespace App\Adapters\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** CORS restricted to the single origin configured in CORS_ORIGIN. */
final class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $request->isMethod('OPTIONS')
            ? response('', 204)
            : $next($request);

        $response->headers->set('Access-Control-Allow-Origin', (string) config('library.cors_origin'));
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
        $response->headers->set('Access-Control-Max-Age', '600');

        return $response;
    }
}
