<?php

namespace Tests\Feature;

use App\Models\ShiftStart;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftStartTimeTest extends TestCase
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

    private function createAvailability(int $workerId, string $date): void
    {
        WorkerAvailability::create([
            'worker_id' => $workerId,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => true,
        ]);
    }

    public function test_admin_can_save_shift_start_time(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->addDay()->toDateString();
        $this->createAvailability($worker->id, $date);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'morning_start_time' => '09:30',
            'workers' => [
                "{$worker->id}_morning" => [
                    'worker_id' => $worker->id,
                    'shift_type' => 'morning',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_starts', [
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 570,
        ]);
    }

    public function test_admin_can_update_shift_start_time(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();

        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 540,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'morning_start_time' => '10:15',
            'workers' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_starts', [
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 615,
        ]);
    }

    public function test_empty_start_time_clears_existing_value(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();

        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 540,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'morning_start_time' => '',
            'workers' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_starts', [
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => null,
        ]);
    }

    public function test_start_time_can_be_saved_without_workers(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'afternoon_start_time' => '14:45',
            'workers' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_starts', [
            'day' => $date,
            'shift_type' => 'afternoon',
            'start_time' => 885,
        ]);
        $this->assertDatabaseCount('worker_shifts', 0);
    }

    public function test_invalid_start_time_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'morning_start_time' => '25:99',
            'workers' => [],
        ]);

        $response->assertSessionHasErrors('morning_start_time');
    }

    public function test_admin_day_route_rejects_invalid_date_format(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/grafik/not-a-date');

        $response->assertNotFound();
    }

    public function test_shift_start_time_does_not_modify_existing_worker_shift_data(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'minutes' => 360,
            'status' => 'worked',
            'worker_from_time' => 480,
            'worker_to_time' => 840,
            'hours_source' => 'admin',
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'morning_start_time' => '09:30',
            'workers' => [
                "{$worker->id}_morning" => [
                    'worker_id' => $worker->id,
                    'shift_type' => 'morning',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'minutes' => 360,
            'status' => 'worked',
            'worker_from_time' => 480,
            'worker_to_time' => 840,
            'hours_source' => 'admin',
            'is_draft' => false,
        ]);
    }

    public function test_day_view_prefills_shift_start_time(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();

        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 570,
        ]);

        $response = $this->actingAs($admin)->get(route('planner.day.index', $date));

        $response->assertStatus(200);
        $response->assertSee('name="morning_start_time"', false);
        $response->assertSee('value="09:30"', false);
    }
}
