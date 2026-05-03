<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Schedule;
use App\Models\ShiftStart;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkerUser(string $username = 'anna', string $firstName = 'Anna', string $lastName = 'Nowak'): User
    {
        $worker = Worker::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $user = User::create([
            'username' => $username,
            'password' => 'password',
            'worker_id' => $worker->id,
        ]);

        $user->role = 'worker';
        $user->save();

        return $user;
    }

    public function test_worker_dashboard_displays_worker_name(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Anna');
        $response->assertSee('Nowak');
        $response->assertDontSee('Jan Kowalski');
    }

    public function test_worker_dashboard_requires_authentication(): void
    {
        $response = $this->get('/strefa-pracownika');

        $response->assertRedirect(route('login'));
    }

    public function test_schedule_inactive_when_disabled(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'disabled']);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Nieaktywny');
        $response->assertDontSee('is-active');
    }

    public function test_schedule_active_with_signup(): void
    {
        $user = $this->createWorkerUser();
        $deadline = now()->addDay();
        $rangeStart = now()->addDays(3);
        $rangeEnd = now()->addDays(7);

        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => $deadline,
            'start_date' => $rangeStart,
            'end_date' => $rangeEnd,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Grafik aktywny do:', false);
        $response->assertSee($deadline->format('d.m.Y H:i'));
        $response->assertSee('is-active');
    }

    public function test_schedule_active_always_no_end_date(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'always']);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Aktywny');
        $response->assertDontSee('Nieaktywny');
        $response->assertSee('is-active');
    }

    public function test_schedule_inactive_when_no_schedule_exists(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Nieaktywny');
    }

    public function test_dashboard_displays_worked_hours_and_salary(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->toDateString(),
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 480,
            'status' => 'worked',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->addDay()->toDateString(),
            'shift_type' => 'afternoon',
            'package_id' => $package->id,
            'minutes' => 300,
            'status' => 'worked',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('13h');
        $response->assertSee('390,00 zł');
    }

    public function test_dashboard_shows_zero_when_no_shifts(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('0h');
        $response->assertSee('0,00 zł');
    }

    public function test_dashboard_excludes_absent_shifts_from_hours(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $package = Package::create(['name' => 'Standard', 'price' => 25.00]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->toDateString(),
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 480,
            'status' => 'worked',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->addDay()->toDateString(),
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 0,
            'status' => 'absent',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('8h');
        $response->assertSee('200,00 zł');
    }

    public function test_admin_cannot_access_worker_dashboard(): void
    {
        $user = User::create([
            'username' => 'admin',
            'password' => 'password',
        ]);

        $user->role = 'admin';
        $user->save();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(403);
    }

    public function test_worker_without_profile_gets_403(): void
    {
        $user = User::create([
            'username' => 'orphan',
            'password' => 'password',
        ]);

        $user->role = 'worker';
        $user->save();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(403);
    }

    public function test_dashboard_shows_next_shift(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->addDay()->toDateString(),
            'shift_type' => 'morning',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->addDay()->toDateString(),
            'shift_type' => 'afternoon',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Zmiana ranna');
        $response->assertSee('Zmiana popołudniowa');
        $response->assertDontSee('Brak zaplanowanych zmian');
    }

    public function test_dashboard_shows_next_shift_start_time(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 08:00:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 630,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Start: 10:30');
    }

    public function test_dashboard_block_label_uses_configured_shift_start_time(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 10:00:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = now()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 630,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Godziny można wpisać po 10:30');
    }

    public function test_dashboard_keeps_today_as_next_when_configured_afternoon_start_not_passed(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 21:30:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        ShiftStart::create([
            'day' => $today,
            'shift_type' => 'afternoon',
            'start_time' => 1320,
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $today,
            'shift_type' => 'afternoon',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $tomorrow,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(Carbon::parse($today)->translatedFormat('j'));
        $response->assertDontSee(Carbon::parse($tomorrow)->translatedFormat('j') . ' ');
    }

    public function test_dashboard_skips_today_when_default_afternoon_start_passed(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 21:30:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $today,
            'shift_type' => 'afternoon',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $tomorrow,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(Carbon::parse($tomorrow)->translatedFormat('j'));
    }

    public function test_dashboard_shows_empty_state_when_no_shifts(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Brak zaplanowanych zmian');
    }

    public function test_dashboard_ignores_past_shifts(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->subDay()->toDateString(),
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Brak zaplanowanych zmian');
    }
}
