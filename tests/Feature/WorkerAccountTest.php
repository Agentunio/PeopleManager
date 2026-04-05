<?php

namespace Tests\Feature;

use App\Mail\WorkerAccountCreated;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    private function createWorker(array $overrides = []): Worker
    {
        return Worker::create(array_merge([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'date_of_birth' => '1990-05-15',
        ], $overrides));
    }

    public function test_admin_can_generate_account(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.store', $worker),
            ['email' => 'jan@example.com']
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        $response->assertJsonStructure(['username']);

        $this->assertDatabaseHas('users', [
            'email' => 'jan@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
        ]);

        $user = User::where('email', 'jan@example.com')->first();
        $this->assertNotNull($user->activation_token);
        $this->assertNotNull($user->activation_expires_at);
        $this->assertEquals('j.kowalski', $user->username);

        Mail::assertSent(WorkerAccountCreated::class, function ($mail) {
            return $mail->hasTo('jan@example.com');
        });
    }

    public function test_cannot_generate_account_without_date_of_birth(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker(['date_of_birth' => null]);

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.store', $worker),
            ['email' => 'jan@example.com']
        );

        $response->assertStatus(422);
    }

    public function test_cannot_generate_duplicate_account(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        User::create([
            'username' => 'existing',
            'password' => 'pass',
            'worker_id' => $worker->id,
            'role' => 'worker',
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.store', $worker),
            ['email' => 'jan@example.com']
        );

        $response->assertStatus(409);
    }

    public function test_email_must_be_unique(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        User::create([
            'username' => 'other',
            'password' => 'pass',
            'email' => 'jan@example.com',
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.store', $worker),
            ['email' => 'jan@example.com']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_admin_can_regenerate_link(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        $user = User::create([
            'username' => 'j.kowalski',
            'password' => 'pass',
            'email' => 'jan@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
            'activation_token' => 'old_token',
            'activation_expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.regenerate', $worker)
        );

        $response->assertStatus(200);
        $user->refresh();
        $this->assertNotEquals('old_token', $user->activation_token);
        $this->assertTrue($user->activation_expires_at->isFuture());

        Mail::assertSent(WorkerAccountCreated::class);
    }

    public function test_cannot_regenerate_link_for_active_account(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        User::create([
            'username' => 'j.kowalski',
            'password' => 'pass',
            'email' => 'jan@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => true,
            'activation_token' => null,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.regenerate', $worker)
        );

        $response->assertStatus(409);
    }

    public function test_admin_can_toggle_account(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        $user = User::create([
            'username' => 'j.kowalski',
            'password' => 'pass',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.toggle', $worker)
        );

        $response->assertStatus(200);
        $this->assertFalse($user->fresh()->is_active);

        $response = $this->actingAs($admin)->postJson(
            route('workers.account.toggle', $worker)
        );

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_inactive_worker_cannot_access_dashboard(): void
    {
        $worker = $this->createWorker();
        $user = User::create([
            'username' => 'j.kowalski',
            'password' => 'pass',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user->fresh())
            ->withSession([])
            ->get(route('worker.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_worker_requires_dob_when_has_account(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        User::create([
            'username' => 'j.kowalski',
            'password' => 'pass',
            'worker_id' => $worker->id,
            'role' => 'worker',
        ]);

        $response = $this->actingAs($admin)->putJson(
            route('workers.update', $worker),
            [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('date_of_birth');
    }
}
