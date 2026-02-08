<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RealAdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('ADMIN_USERNAME');
        $password = env('ADMIN_PASSWORD');

        if (!$username || !$password) {
            $this->command->error('Ustaw ADMIN_USERNAME i ADMIN_PASSWORD w pliku .env');
            return;
        }

        User::updateOrCreate(
            ['username' => $username],
            [
                'password' => Hash::make($password),
                'role' => 'admin',
            ]
        );
    }
}
