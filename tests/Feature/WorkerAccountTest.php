<?php

namespace Tests\Feature;

use App\Mail\WorkerAccountCreated;
use App\Models\User;
use App\Models\Worker;
use App\Notifications\QueuedResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'admin',
            'email' => 'admin@example.test',
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

        Mail::assertQueued(WorkerAccountCreated::class, function ($mail) {
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
            'email' => 'existing@example.test',
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

        Mail::assertQueued(WorkerAccountCreated::class);
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
            'email' => 'j.kowalski@example.test',
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

    public function test_admin_can_send_password_reset_link_to_active_worker_account(): void
    {
        Notification::fake();
        $admin = $this->createAdmin();
        $worker = $this->createWorker();
        $user = User::create([
            'username' => 'j.kowalski',
            'email' => 'j.kowalski@example.test',
            'password' => 'pass',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('workers.account.password-reset', $worker))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_admin_cannot_send_password_reset_link_to_inactive_worker_account(): void
    {
        Notification::fake();
        $admin = $this->createAdmin();
        $worker = $this->createWorker();
        $user = User::create([
            'username' => 'j.kowalski',
            'email' => 'j.kowalski@example.test',
            'password' => 'pass',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->postJson(route('workers.account.password-reset', $worker))
            ->assertConflict();

        Notification::assertNothingSent();
    }

    public function test_inactive_worker_cannot_access_dashboard(): void
    {
        $worker = $this->createWorker();
        $user = User::create([
            'username' => 'j.kowalski',
            'email' => 'j.kowalski@example.test',
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
            'email' => 'j.kowalski@example.test',
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

    public function test_admin_can_clear_optional_worker_fields(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker([
            'phone' => '+48 500 600 700',
            'address' => 'Testowa 1',
            'contract_from' => '2026-01-01',
            'contract_to' => '2026-12-31',
        ]);

        $this->actingAs($admin)->putJson(route('workers.update', $worker), [
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'phone' => null,
            'address' => null,
            'date_of_birth' => null,
            'contract_from' => null,
            'contract_to' => null,
            'is_student' => false,
            'is_employed' => true,
        ])->assertOk();

        $this->assertDatabaseHas('workers', [
            'id' => $worker->id,
            'phone' => null,
            'address' => null,
            'date_of_birth' => null,
            'contract_from' => null,
            'contract_to' => null,
        ]);
    }

    public function test_worker_status_rejects_unknown_boolean_values(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->postJson(route('workers.store'), [
            'first_name' => 'Jan',
            'last_name' => 'Nowak',
            'is_student' => 'invalid',
            'is_employed' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'is_student',
            'is_employed',
        ]);
    }

    private function createActiveWorkerAccount(string $suffix): Worker
    {
        $worker = $this->createWorker(['last_name' => 'Kowalski'.$suffix]);

        $user = User::create([
            'username' => 'worker'.$suffix,
            'email' => "worker{$suffix}@example.test",
            'password' => 'password',
            'worker_id' => $worker->id,
            'role' => 'worker',
        ]);
        $user->is_active = true;
        $user->save();

        return $worker;
    }

    public function test_admin_password_resets_for_different_workers_share_no_throttle_bucket(): void
    {
        Notification::fake();
        $admin = $this->createAdmin();

        foreach (range(1, 6) as $index) {
            $worker = $this->createActiveWorkerAccount((string) $index);

            $this->actingAs($admin)
                ->postJson(route('workers.account.password-reset', $worker))
                ->assertStatus(200);
        }
    }

    public function test_admin_password_reset_for_same_worker_is_throttled(): void
    {
        Notification::fake();
        $admin = $this->createAdmin();
        $worker = $this->createActiveWorkerAccount('1');

        $this->actingAs($admin)
            ->postJson(route('workers.account.password-reset', $worker))
            ->assertStatus(200);

        foreach (range(2, 5) as $attempt) {
            $response = $this->actingAs($admin)->postJson(route('workers.account.password-reset', $worker));
            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->actingAs($admin)
            ->postJson(route('workers.account.password-reset', $worker))
            ->assertStatus(429);
    }
}
