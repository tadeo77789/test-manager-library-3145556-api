<?php

declare(strict_types=1);

namespace App\Adapters\Http\Middleware;

use App\Application\Ports\TokenIssuer;
use App\Domain\Errors\Unauthenticated;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bearer token check. Stores the claims in the request attribute "claims". */
final class Authenticate
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token === null ? null : $this->tokens->verify($token);
        if ($claims === null) {
            throw new Unauthenticated();
        }
        $request->attributes->set('claims', $claims);

        return $next($request);
    }
}
