<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerDayViewTest extends TestCase
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

    private function createWorker(string $firstName, string $lastName): Worker
    {
        return Worker::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    private function shift(int $workerId, string $date, string $shiftType, array $attrs = []): WorkerShift
    {
        return WorkerShift::create(array_merge([
            'worker_id' => $workerId,
            'day' => $date,
            'shift_type' => $shiftType,
            'is_draft' => false,
        ], $attrs));
    }

    public function test_assigned_counter_skips_absent_workers_and_counts_substitutes(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();

        $present = $this->createWorker('Obecna', 'Kowalska');
        $absent = $this->createWorker('Nieobecny', 'Nowak');
        $substitute = $this->createWorker('Zastepca', 'Lis');

        $this->shift($present->id, $date, 'morning');
        $absentShift = $this->shift($absent->id, $date, 'morning', ['status' => 'absent']);
        $this->shift($substitute->id, $date, 'morning', [
            'substituted_for_shift_id' => $absentShift->id,
        ]);

        $response = $this->actingAs($admin)->get(route('planner.day.index', $date));

        $response->assertOk();
        // 3 osoby na tablicy, ale nieobecny nie wychodzi na zmianę -> 2.
        $response->assertSee('<span id="morning-count">2</span>', false);
        $response->assertSee('<span id="afternoon-count">0</span>', false);
    }

    public function test_assigned_counter_is_zero_when_everyone_is_absent(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();
        $worker = $this->createWorker('Sam', 'Nieobecny');

        $this->shift($worker->id, $date, 'afternoon', ['status' => 'absent']);

        $response = $this->actingAs($admin)->get(route('planner.day.index', $date));

        $response->assertOk();
        $response->assertSee('<span id="afternoon-count">0</span>', false);
    }

    public function test_assigned_counter_counts_every_worker_without_absence(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();

        foreach ([['Ala', 'Aa'], ['Ola', 'Bb'], ['Ela', 'Cc']] as $index => [$first, $last]) {
            $worker = $this->createWorker($first, $last);
            $this->shift($worker->id, $date, $index === 0 ? 'morning' : 'afternoon');
        }

        $response = $this->actingAs($admin)->get(route('planner.day.index', $date));

        $response->assertOk();
        $response->assertSee('<span id="morning-count">1</span>', false);
        $response->assertSee('<span id="afternoon-count">2</span>', false);
    }
}
