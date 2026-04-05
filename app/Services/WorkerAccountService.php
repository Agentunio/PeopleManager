<?php

namespace App\Services;

use App\Mail\WorkerAccountCreated;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WorkerAccountService
{
    public function __construct(
        private UsernameGeneratorService $usernameGenerator
    ) {}

    public function createAccount(Worker $worker, string $email): User
    {
        $username = $this->usernameGenerator->generate($worker->first_name, $worker->last_name);
        $token = Str::random(64);

        $user = User::create([
            'username' => $username,
            'password' => Str::random(32),
            'email' => $email,
            'role' => 'worker',
            'worker_id' => $worker->id,
            'is_active' => false,
            'activation_token' => hash('sha256', $token),
            'activation_expires_at' => now()->addDay(),
        ]);

        Mail::to($user->email)->send(new WorkerAccountCreated($user, $token));

        return $user;
    }

    public function regenerateActivationLink(User $user): void
    {
        $token = Str::random(64);

        $user->update([
            'activation_token' => hash('sha256', $token),
            'activation_expires_at' => now()->addDay(),
        ]);

        Mail::to($user->email)->send(new WorkerAccountCreated($user, $token));
    }

    public function toggleActive(User $user): bool
    {
        $user->update(['is_active' => !$user->is_active]);

        return $user->is_active;
    }
}
