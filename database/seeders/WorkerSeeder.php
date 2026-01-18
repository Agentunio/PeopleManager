<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WorkerSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'krzys'],
            [
                'password' => Hash::make('krzys123'),
                'role' => 'worker',
            ]
        );
    }
}
