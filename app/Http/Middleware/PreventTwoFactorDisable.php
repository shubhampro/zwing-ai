<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventTwoFactorDisable
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('two-factor.disable')) {
            abort(403, 'Two-factor authentication is required and cannot be disabled.');
        }

        return $next($request);
    }
}
