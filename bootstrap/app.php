<?php

use App\Http\Middleware\CheckLoginAttempts;
use App\Http\Middleware\CheckUserRole;
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
            'check.login.attempts' => CheckLoginAttempts::class,
            'check.user.role' => CheckUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            $isGuestFormRequest = $request->is('/')
                || $request->routeIs(
                    'account.verify',
                    'account.activate.store',
                    'password.email',
                    'password.update'
                );

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

            if ($request->routeIs('password.email')) {
                return redirect()->route('password.request')->withErrors([
                    'email' => 'Sesja wygasła. Odśwież formularz i spróbuj ponownie.',
                ]);
            }

            if ($request->routeIs('password.update')) {
                $token = (string) $request->input('token');
                $email = mb_strtolower(trim((string) $request->input('email')));

                if (
                    ! preg_match('/\A[A-Za-z0-9]{64}\z/', $token)
                    || strlen($email) > 255
                    || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                ) {
                    return redirect()->route('password.request')->withErrors([
                        'email' => 'Sesja wygasła. Otwórz ponownie link z wiadomości.',
                    ]);
                }

                return redirect()->route('password.reset', [
                    'token' => $token,
                    'email' => $email,
                ])->withErrors([
                    'password' => 'Sesja wygasła. Odśwież formularz i spróbuj ponownie.',
                ]);
            }

            return redirect()->route('login')->withErrors([
                'login' => 'Sesja wygasła. Odśwież formularz i spróbuj ponownie.',
            ]);
        });
    })->create();
