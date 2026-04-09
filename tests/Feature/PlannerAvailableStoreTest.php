<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerAvailableStoreTest extends TestCase
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

    public function test_admin_can_set_signup_schedule(): void
    {
        $admin = $this->createAdmin();

        $deadline = now()->addDay()->format('Y-m-d H:i');
        $start = now()->addDays(3)->format('Y-m-d');
        $end = now()->addDays(7)->format('Y-m-d');

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'signup',
            'signup_deadline' => $deadline,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('schedules', [
            'id' => 1,
            'type' => 'signup',
        ]);

        $schedule = Schedule::find(1);
        $this->assertNotNull($schedule->signup_deadline);
        $this->assertTrue($schedule->isActive());
    }

    public function test_admin_can_set_always(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'always',
        ]);

        $response->assertRedirect();

        $schedule = Schedule::find(1);
        $this->assertSame('always', $schedule->type);
        $this->assertNull($schedule->signup_deadline);
        $this->assertTrue($schedule->isActive());
    }

    public function test_admin_can_set_disabled(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'disabled',
        ]);

        $response->assertRedirect();

        $schedule = Schedule::find(1);
        $this->assertSame('disabled', $schedule->type);
        $this->assertFalse($schedule->isActive());
    }

    public function test_signup_requires_deadline_and_range(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'signup',
        ]);

        $response->assertSessionHasErrors(['signup_deadline', 'start_date', 'end_date']);
    }

    public function test_signup_deadline_must_be_in_future(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'signup',
            'signup_deadline' => now()->subHour()->format('Y-m-d H:i'),
            'start_date' => now()->addDays(3)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('signup_deadline');
    }

    public function test_signup_deadline_must_be_before_start_date(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'signup',
            'signup_deadline' => now()->addDays(5)->format('Y-m-d H:i'),
            'start_date' => now()->addDays(3)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['signup_deadline', 'start_date']);
    }

    public function test_signup_end_date_must_be_after_or_equal_start(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'signup',
            'signup_deadline' => now()->addDay()->format('Y-m-d H:i'),
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_signup_accepts_equal_start_and_end(): void
    {
        $admin = $this->createAdmin();

        $day = now()->addDays(3)->format('Y-m-d');

        $response = $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'signup',
            'signup_deadline' => now()->addDay()->format('Y-m-d H:i'),
            'start_date' => $day,
            'end_date' => $day,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('schedules', ['type' => 'signup']);
    }

    public function test_non_admin_cannot_store(): void
    {
        $user = User::create([
            'username' => 'worker1',
            'password' => 'password',
            'role' => 'worker',
        ]);

        $response = $this->actingAs($user)->post(route('planner.schedule.store'), [
            'type' => 'disabled',
        ]);

        $response->assertStatus(403);
    }

    public function test_non_signup_type_clears_range_fields(): void
    {
        $admin = $this->createAdmin();

        Schedule::create([
            'id' => 1,
            'type' => 'signup',
            'signup_deadline' => now()->addDay(),
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(7),
        ]);

        $this->actingAs($admin)->post(route('planner.schedule.store'), [
            'type' => 'disabled',
        ]);

        $schedule = Schedule::find(1);
        $this->assertNull($schedule->signup_deadline);
        $this->assertNull($schedule->start_date);
        $this->assertNull($schedule->end_date);
    }
}
