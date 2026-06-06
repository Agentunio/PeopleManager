<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'check.login.attempts' => \App\Http\Middleware\CheckLoginAttempts::class,
            'check.user.role' => \App\Http\Middleware\CheckUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, \Throwable $e, Request $request) {
            $isGuestFormRequest = $request->is('/')
                || $request->routeIs('account.verify', 'account.activate.store');

            if (
                $response->getStatusCode() !== 419
                || ! $e->getPrevious() instanceof TokenMismatchException
                || ! $request->isMethod('post')
                || ! $isGuestFormRequest
            ) {
                return $response;
            }

            if (auth()->check()) {
                return match (auth()->user()->role) {
                    'worker' => redirect()->route('worker.dashboard'),
                    'admin' => redirect()->route('dashboard'),
                    default => redirect()->route('login'),
                };
            }

            return redirect()->route('login')->withErrors([
                'login' => 'Sesja wygasła. Odśwież formularz i spróbuj ponownie.',
            ]);
        });
    })->create();
