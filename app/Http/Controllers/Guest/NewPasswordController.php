<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\ResetPasswordRequest;
use App\Models\User;
use App\Services\UserSessionInvalidator;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('guest.password.reset', [
            'token' => $token,
            'email' => trim((string) $request->query('email')),
        ]);
    }

    public function store(
        ResetPasswordRequest $request,
        UserSessionInvalidator $sessionInvalidator
    ): RedirectResponse {
        $credentials = [
            ...$request->validated(),
            'is_active' => true,
        ];

        $status = Password::broker()->reset(
            $credentials,
            function (User $user, string $password) use ($sessionInvalidator): void {
                DB::transaction(function () use ($user, $password, $sessionInvalidator): void {
                    $user->forceFill(['password' => $password]);
                    $user->setRememberToken(Str::random(60));
                    $user->save();

                    $sessionInvalidator->invalidate($user);
                });

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Link do zmiany hasła jest nieprawidłowy lub wygasł.']);
        }

        return redirect()->route('login')->with('success', 'Hasło zostało zmienione. Możesz się zalogować.');
    }
}
