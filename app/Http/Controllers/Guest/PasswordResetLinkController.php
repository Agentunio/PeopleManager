<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('guest.password.forgot');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        Password::broker()->sendResetLink([
            'email' => $request->validated('email'),
            'is_active' => true,
        ]);

        return back()->with(
            'status',
            'Jeśli aktywne konto z tym adresem istnieje, wysłaliśmy link do zmiany hasła.'
        );
    }
}
