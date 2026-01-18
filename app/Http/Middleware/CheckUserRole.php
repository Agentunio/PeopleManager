<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{

    public function handle(Request $request, Closure $next, $roleuser): Response
    {
        $role = auth()->user()->role;

        if ($role == $roleuser) {
            return $next($request);
        }else{
            return redirect()->back();
        }
    }
}
