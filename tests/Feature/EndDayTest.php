<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndDayTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $user = User::create([
            'username' => 'admin',
            'password' => 'password',
        ]);
        $user->role = 'admin';
        $user->save();

        return $user;
    }

    private function createWorker(string $firstName, string $lastName): Worker
    {
        return Worker::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    private function shift(int $workerId, string $date, string $type, array $attrs = []): WorkerShift
    {
        return WorkerShift::create(array_merge([
            'worker_id' => $workerId,
            'day' => $date,
            'shift_type' => $type,
        ], $attrs));
    }

    public function test_saving_settlement_without_hours_does_not_mark_untouched_workers_as_admin_approved(): void
    {
        $admin = $this->createAdmin();
        $workerA = $this->createWorker('Adam', 'Kowalski');
        $workerB = $this->createWorker('Bartek', 'Nowak');
        $package = Package::create(['name' => 'Test 10', 'price' => 10]);
        $date = now()->toDateString();

        $this->shift($workerA->id, $date, 'morning');
        $this->shift($workerB->id, $date, 'morning');

        $response = $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$workerA->id}_morning" => [
                    'id' => $workerA->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'package' => $package->id,
                    'from_hour' => 8,
                    'from_minute' => 0,
                    'to_hour' => 14,
                    'to_minute' => 0,
                ],
                "{$workerB->id}_morning" => [
                    'id' => $workerB->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'from_hour' => '',
                    'from_minute' => '',
                    'to_hour' => '',
                    'to_minute' => '',
                ],
            ],
        ]);

        $response->assertRedirect();

        $a = WorkerShift::where('worker_id', $workerA->id)->first();
        $b = WorkerShift::where('worker_id', $workerB->id)->first();

        $this->assertEquals('admin', $a->hours_source);
        $this->assertEquals(360, $a->minutes);

        $this->assertNull($b->hours_source);
        $this->assertNull($b->minutes);
    }

    public function test_worker_can_submit_own_hours_when_admin_saved_settlement_without_their_hours(): void
    {
        $admin = $this->createAdmin();
        $workerA = $this->createWorker('Adam', 'Kowalski');
        $workerB = $this->createWorker('Bartek', 'Nowak');

        $workerBUser = User::create([
            'username' => 'bartek',
            'password' => 'password',
            'worker_id' => $workerB->id,
        ]);
        $workerBUser->role = 'worker';
        $workerBUser->save();

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        $this->shift($workerA->id, $date, 'morning');
        $this->shift($workerB->id, $date, 'morning');

        $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$workerA->id}_morning" => [
                    'id' => $workerA->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'from_hour' => 8,
                    'from_minute' => 0,
                    'to_hour' => 14,
                    'to_minute' => 0,
                ],
                "{$workerB->id}_morning" => [
                    'id' => $workerB->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                ],
            ],
        ]);

        $response = $this->actingAs($workerBUser)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '09:00', 'to_time' => '15:00']
        );

        $response->assertOk();

        $b = WorkerShift::where('worker_id', $workerB->id)->first();
        $this->assertEquals('worker', $b->hours_source);
        $this->assertEquals(9 * 60, $b->worker_from_time);
        $this->assertEquals(15 * 60, $b->worker_to_time);
    }

    public function test_admin_save_without_hours_keeps_worker_reported_hours_intact(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Celina', 'Wilk');
        $date = now()->toDateString();

        $this->shift($worker->id, $date, 'morning', [
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
            'hours_source' => 'worker',
        ]);

        $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$worker->id}_morning" => [
                    'id' => $worker->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                ],
            ],
        ]);

        $shift = WorkerShift::where('worker_id', $worker->id)->first();
        $this->assertEquals('worker', $shift->hours_source);
        $this->assertEquals(8 * 60, $shift->worker_from_time);
        $this->assertEquals(14 * 60, $shift->worker_to_time);
    }

    public function test_admin_save_with_hours_overrides_worker_reported_hours(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Dorota', 'Lis');
        $date = now()->toDateString();

        $this->shift($worker->id, $date, 'morning', [
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
            'hours_source' => 'worker',
        ]);

        $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$worker->id}_morning" => [
                    'id' => $worker->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'from_hour' => 9,
                    'from_minute' => 0,
                    'to_hour' => 17,
                    'to_minute' => 30,
                ],
            ],
        ]);

        $shift = WorkerShift::where('worker_id', $worker->id)->first();
        $this->assertEquals('admin', $shift->hours_source);
        $this->assertEquals(8 * 60 + 30, $shift->minutes);
    }
}
