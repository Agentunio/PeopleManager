<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RealAdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('ADMIN_USERNAME');
        $password = env('ADMIN_PASSWORD');
        $email = mb_strtolower(trim((string) env('ADMIN_EMAIL')));

        if (! $username || ! $password || ! $email) {
            $this->command->error('Ustaw ADMIN_USERNAME, ADMIN_PASSWORD i ADMIN_EMAIL w pliku .env');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->error('ADMIN_EMAIL musi być prawidłowym adresem e-mail.');

            return;
        }

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'password' => $password,
                'email' => $email,
            ]
        );

        $user->role = 'admin';
        $user->save();
    }
}
