<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateWorkerAccountRequest;
use App\Models\Worker;
use App\Services\WorkerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class WorkerAccountController extends Controller
{
    public function __construct(
        private WorkerAccountService $accountService
    ) {}

    public function store(GenerateWorkerAccountRequest $request, Worker $worker): JsonResponse
    {
        abort_unless(! $worker->hasAccount(), 409, 'Pracownik ma już konto');
        abort_unless($worker->date_of_birth !== null, 422, 'Pracownik musi mieć uzupełnioną datę urodzenia');

        $user = $this->accountService->createAccount($worker, $request->validated('email'));

        return response()->json([
            'status' => 'success',
            'message' => 'Konto zostało wygenerowane. Link aktywacyjny wysłany na '.$user->email,
            'username' => $user->username,
        ]);
    }

    public function regenerateLink(Worker $worker): JsonResponse
    {
        $user = $worker->user;
        abort_unless($user !== null, 404, 'Pracownik nie ma konta');
        abort_unless(! $user->is_active, 409, 'Konto jest już aktywowane');

        $this->accountService->regenerateActivationLink($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Link aktywacyjny został wysłany ponownie',
        ]);
    }

    public function sendPasswordResetLink(Worker $worker): JsonResponse
    {
        $user = $worker->user;
        abort_unless($user !== null, 404, 'Pracownik nie ma konta');
        abort_unless($user->is_active, 409, 'Konto nie jest aktywne');
        abort_unless(filled($user->email), 422, 'Konto nie ma adresu e-mail');

        $status = Password::broker()->sendResetLink([
            'email' => $user->email,
            'is_active' => true,
        ]);

        abort_unless(
            $status === Password::RESET_LINK_SENT,
            422,
            'Nie udało się wysłać linku do ustawienia nowego hasła'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Link do ustawienia nowego hasła został wysłany na '.$user->email,
        ]);
    }

    public function toggle(Worker $worker): JsonResponse
    {
        $user = $worker->user;
        abort_unless($user !== null, 404, 'Pracownik nie ma konta');

        $isActive = $this->accountService->toggleActive($user);
        $status = $isActive ? 'aktywowane' : 'dezaktywowane';

        return response()->json([
            'status' => 'success',
            'message' => "Konto zostało {$status}",
            'is_active' => $isActive,
        ]);
    }
}
