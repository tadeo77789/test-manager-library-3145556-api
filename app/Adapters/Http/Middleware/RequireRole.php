<?php

declare(strict_types=1);

namespace App\Adapters\Http\Middleware;

use App\Domain\Errors\ForbiddenRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Role authorization per endpoint (runs after Authenticate, before any resource lookup). */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $claims = $request->attributes->get('claims');
        if ($claims === null || ! in_array($claims->role, $roles, true)) {
            throw new ForbiddenRole();
        }

        return $next($request);
    }
}
