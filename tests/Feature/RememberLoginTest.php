<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RememberLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_remember_me_sets_remember_token(): void
    {
        $user = User::create([
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->post('/', [
            'login' => 'admin',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_without_remember_me_leaves_remember_token_empty(): void
    {
        $user = User::create([
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->post('/', [
            'login' => 'admin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertNull($user->fresh()->remember_token);
    }
}