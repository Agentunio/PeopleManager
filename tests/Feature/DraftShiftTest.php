<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftShiftTest extends TestCase
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

    private function createWorkerUser(): User
    {
        $worker = Worker::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
        ]);

        $user = User::create([
            'username' => 'anna',
            'password' => 'password',
            'worker_id' => $worker->id,
        ]);

        $user->role = 'worker';
        $user->save();

        return $user;
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

    public function test_admin_can_save_shift_as_draft(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->addDay()->toDateString();
        $this->createAvailability($worker->id, $date);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [
                "{$worker->id}_morning" => [
                    'worker_id' => $worker->id,
                    'shift_type' => 'morning',
                ],
            ],
            'is_draft' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Grafik zapisany jako szkic');

        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => true,
        ]);
    }

    public function test_admin_can_save_shift_as_published(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->addDay()->toDateString();
        $this->createAvailability($worker->id, $date);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [
                "{$worker->id}_morning" => [
                    'worker_id' => $worker->id,
                    'shift_type' => 'morning',
                ],
            ],
            'is_draft' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Grafik został zapisany');

        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'is_draft' => false,
        ]);
    }

    public function test_draft_can_be_published(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [
                "{$worker->id}_morning" => [
                    'worker_id' => $worker->id,
                    'shift_type' => 'morning',
                ],
            ],
            'is_draft' => '0',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'is_draft' => false,
        ]);
    }

    public function test_draft_shifts_hidden_from_worker_dashboard(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => true,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Zmiana ranna');
    }

    public function test_draft_shifts_hidden_from_worker_schedule(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $weekStart = now()->addWeek()->startOfWeek();
        $date = $weekStart->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => true,
        ]);

        $response = $this->actingAs($user)->get(route('worker.schedule', $weekStart->format('d-m-Y')));

        $response->assertStatus(200);

        $shifts = WorkerShift::published()
            ->where('worker_id', $worker->id)
            ->where('day', $date)
            ->get();

        $this->assertEmpty($shifts);
    }

    public function test_published_scope_excludes_drafts(): void
    {
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => true,
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'afternoon',
            'is_draft' => false,
        ]);

        $published = WorkerShift::published()->get();
        $drafts = WorkerShift::draft()->get();

        $this->assertCount(1, $published);
        $this->assertCount(1, $drafts);
        $this->assertEquals('afternoon', $published->first()->shift_type);
        $this->assertEquals('morning', $drafts->first()->shift_type);
    }

    public function test_day_view_shows_draft_banner(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('planner.day.index', $date));

        $response->assertStatus(200);
        $response->assertSee('Grafik jest szkicem!');
    }

    public function test_day_view_hides_draft_banner_for_published(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('planner.day.index', $date));

        $response->assertStatus(200);
        $response->assertDontSee('SZKIC');
    }

    public function test_planner_calendar_shows_draft_badge(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = now()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'is_draft' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('planner.index'));

        $response->assertStatus(200);
        $response->assertSee('Szkic');
        $response->assertSee('calendar-day-draft');
    }
}
