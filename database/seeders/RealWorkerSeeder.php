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

        if (!$username || !$password) {
            $this->command->error('Ustaw WORKER_USERNAME i WORKER_PASSWORD w pliku .env');
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
                'password' => $password,
                'role' => 'worker',
                'worker_id' => $worker->id,
                'is_active' => true,
            ]
        );
    }
}
