<?php

namespace App\Http\Middleware;

use App\Models\LoginAttempt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLoginAttempts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (LoginAttempt::isBlocked($request->ip())) {
            return redirect()->route('login')->withErrors([
                'login' => 'Spróbuj ponownie później',
            ]);
        }

        return $next($request);
    }
}
