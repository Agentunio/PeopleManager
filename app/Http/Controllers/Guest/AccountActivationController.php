<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\ActivateAccountRequest;
use App\Http\Requests\Guest\VerifyAccountActivationRequest;
use App\Mail\WorkerAccountActivated;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AccountActivationController extends Controller
{
    public function show(string $token): View
    {
        $this->findUserByToken($token);

        return view('guest.activate', [
            'token' => $token,
            'step' => 'verify',
        ]);
    }

    public function verify(VerifyAccountActivationRequest $request, string $token): View|RedirectResponse
    {
        $user = $this->findUserByToken($token);

        if ($request->validated('date_of_birth') !== $user->worker->date_of_birth->format('Y-m-d')) {
            return back()->withErrors(['date_of_birth' => 'Podana data urodzenia jest nieprawidłowa.']);
        }

        $request->session()->put('activation_verified_'.$token, true);

        return view('guest.activate', [
            'token' => $token,
            'step' => 'password',
        ]);
    }

    public function activate(ActivateAccountRequest $request, string $token): RedirectResponse
    {
        $user = $this->findUserByToken($token);

        abort_unless($request->session()->get('activation_verified_'.$token) === true, 403, 'Weryfikacja tożsamości nie została przeprowadzona.');

        $user->update([
            'password' => $request->validated('password'),
            'is_active' => true,
            'activation_token' => null,
            'activation_expires_at' => null,
        ]);

        Mail::to($user->email)->send(new WorkerAccountActivated($user));

        $request->session()->forget('activation_verified_'.$token);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Konto zostało aktywowane. Możesz się zalogować.');
    }

    private function findUserByToken(string $token): User
    {
        $user = User::where('activation_token', hash('sha256', $token))
            ->with('worker')
            ->first();

        abort_unless($user !== null, 404, 'Link aktywacyjny jest nieprawidłowy.');
        abort_unless(! $user->hasExpiredActivation(), 410, 'Link aktywacyjny wygasł. Skontaktuj się z administratorem.');

        return $user;
    }
}
