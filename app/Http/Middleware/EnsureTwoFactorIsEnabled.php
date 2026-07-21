<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! Features::enabled(Features::twoFactorAuthentication())
            || $user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        $request->session()->put('two_factor_enforcement_notice', true);

        return redirect()->route('security.edit');
    }
}
