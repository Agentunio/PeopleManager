<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class RealWorkerSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('WORKER_USERNAME');
        $password = env('WORKER_PASSWORD');
        $email = mb_strtolower(trim((string) env('WORKER_EMAIL')));

        if (! $username || ! $password || ! $email) {
            $this->command->error('Ustaw WORKER_USERNAME, WORKER_PASSWORD i WORKER_EMAIL w pliku .env');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->error('WORKER_EMAIL musi być prawidłowym adresem e-mail.');

            return;
        }

        $worker = Worker::firstOrCreate(
            ['first_name' => 'Jan', 'last_name' => 'Kowalski'],
            [
                'is_student' => false,
                'is_employed' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => $username],
            [
                'email' => $email,
                'password' => $password,
                'role' => 'worker',
                'worker_id' => $worker->id,
                'is_active' => true,
            ]
        );
    }
}
