<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageShift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use App\Rules\WorkerAvailableForShift;
use App\Services\PlannerDayShiftSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlannerDayMoveTest extends TestCase
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

    private function createAvailability(int $workerId, string $date): void
    {
        WorkerAvailability::create([
            'worker_id' => $workerId,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => true,
        ]);
    }

    private function submittedWorker(Worker $worker, string $shiftType): array
    {
        return [
            "{$worker->id}_{$shiftType}" => [
                'worker_id' => $worker->id,
                'shift_type' => $shiftType,
            ],
        ];
    }

    public function test_moving_worker_between_shifts_preserves_work_details(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Jan', 'Kowalski');
        $date = now()->addDay()->toDateString();
        $this->createAvailability($worker->id, $date);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'minutes' => 480,
            'worker_from_time' => 480,
            'worker_to_time' => 960,
            'approved_from_time' => 510,
            'approved_to_time' => 990,
            'hours_source' => 'worker',
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => $this->submittedWorker($worker, 'afternoon'),
            'is_draft' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);
        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'afternoon',
            'status' => 'worked',
            'minutes' => 480,
            'worker_from_time' => 480,
            'worker_to_time' => 960,
            'approved_from_time' => 510,
            'approved_to_time' => 990,
            'hours_source' => 'worker',
        ]);
    }

    public function test_moving_absent_worker_preserves_absent_status(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Jan', 'Kowalski');
        $date = now()->addDay()->toDateString();
        $this->createAvailability($worker->id, $date);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => $this->submittedWorker($worker, 'afternoon'),
            'is_draft' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'afternoon',
            'status' => 'absent',
            'minutes' => 0,
        ]);
    }

    public function test_moving_substitute_between_shifts_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $absent = $this->createWorker('Jan', 'Kowalski');
        $substitute = $this->createWorker('Anna', 'Nowak');
        $date = now()->addDay()->toDateString();
        $this->createAvailability($absent->id, $date);
        $this->createAvailability($substitute->id, $date);

        $absentShift = WorkerShift::create([
            'worker_id' => $absent->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => false,
        ]);

        WorkerShift::create([
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'substituted_for_shift_id' => $absentShift->id,
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [
                ...$this->submittedWorker($absent, 'morning'),
                ...$this->submittedWorker($substitute, 'afternoon'),
            ],
            'is_draft' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('workers');

        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
            'substituted_for_shift_id' => $absentShift->id,
        ]);
        $this->assertDatabaseMissing('worker_shifts', [
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'afternoon',
        ]);
    }

    public function test_moving_absent_worker_with_substitute_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $absent = $this->createWorker('Jan', 'Kowalski');
        $substitute = $this->createWorker('Anna', 'Nowak');
        $date = now()->addDay()->toDateString();
        $this->createAvailability($absent->id, $date);
        $this->createAvailability($substitute->id, $date);

        $absentShift = WorkerShift::create([
            'worker_id' => $absent->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => false,
        ]);

        WorkerShift::create([
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'substituted_for_shift_id' => $absentShift->id,
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [
                ...$this->submittedWorker($absent, 'afternoon'),
                ...$this->submittedWorker($substitute, 'morning'),
            ],
            'is_draft' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('workers');

        $this->assertDatabaseHas('worker_shifts', [
            'id' => $absentShift->id,
            'shift_type' => 'morning',
            'status' => 'absent',
        ]);
    }

    public function test_resave_without_changes_preserves_substitution(): void
    {
        $admin = $this->createAdmin();
        $absent = $this->createWorker('Jan', 'Kowalski');
        $substitute = $this->createWorker('Anna', 'Nowak');
        $date = now()->addDay()->toDateString();

        $absentShift = WorkerShift::create([
            'worker_id' => $absent->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => false,
        ]);

        WorkerShift::create([
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'substituted_for_shift_id' => $absentShift->id,
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [
                ...$this->submittedWorker($absent, 'morning'),
                ...$this->submittedWorker($substitute, 'morning'),
            ],
            'is_draft' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $absent->id,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
        ]);
        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $substitute->id,
            'shift_type' => 'morning',
            'status' => 'worked',
            'substituted_for_shift_id' => $absentShift->id,
        ]);
    }

    public function test_removing_absent_worker_and_substitute_deletes_both(): void
    {
        $admin = $this->createAdmin();
        $absent = $this->createWorker('Jan', 'Kowalski');
        $substitute = $this->createWorker('Anna', 'Nowak');
        $date = now()->addDay()->toDateString();

        $absentShift = WorkerShift::create([
            'worker_id' => $absent->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => false,
        ]);

        WorkerShift::create([
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'substituted_for_shift_id' => $absentShift->id,
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [],
            'is_draft' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('worker_shifts', 0);
    }

    public function test_removing_original_while_substitute_remains_in_payload_deletes_pair(): void
    {
        $admin = $this->createAdmin();
        $absent = $this->createWorker('Jan', 'Kowalski');
        $substitute = $this->createWorker('Anna', 'Nowak');
        $date = now()->addDay()->toDateString();
        $original = WorkerShift::create([
            'worker_id' => $absent->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
            'substituted_for_shift_id' => $original->id,
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => $this->submittedWorker($substitute, 'morning'),
            'is_draft' => '0',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('worker_shifts', 0);
    }

    public function test_full_save_rejects_settled_day_atomically(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Jan', 'Rozliczony');
        $date = now()->subDay()->toDateString();
        $package = Package::create(['name' => 'Standard', 'price' => 1]);
        $shift = WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'minutes' => 480,
            'is_draft' => false,
        ]);
        foreach (['morning', 'afternoon'] as $shiftType) {
            PackageShift::create([
                'day' => $date,
                'shift_type' => $shiftType,
                'package_id' => $package->id,
                'packages_count' => 1,
            ]);
        }

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [],
            'morning_start_time' => '10:15',
            'is_draft' => '1',
        ]);

        $response->assertRedirect()->assertSessionHasErrors('shift');
        $this->assertDatabaseHas('worker_shifts', ['id' => $shift->id, 'is_draft' => false]);
        $this->assertDatabaseMissing('shift_starts', ['day' => $date]);
    }

    public function test_duplicate_worker_shift_assignment_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Jan', 'Duplikat');
        $date = now()->addDay()->toDateString();
        $this->createAvailability($worker->id, $date);

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => [
                'first' => ['worker_id' => $worker->id, 'shift_type' => 'morning'],
                'second' => ['worker_id' => $worker->id, 'shift_type' => 'morning'],
            ],
            'is_draft' => '0',
        ]);

        $response->assertRedirect()->assertSessionHasErrors('workers.second.shift_type');
        $this->assertDatabaseCount('worker_shifts', 0);
    }

    public function test_invalid_draft_flag_is_rejected(): void
    {
        $date = now()->addDay()->toDateString();

        $this->actingAs($this->createAdmin())->post(route('planner.day.shift', $date), [
            'workers' => [],
            'is_draft' => 'draft',
        ])->assertSessionHasErrors('is_draft');

        $this->assertDatabaseCount('worker_shifts', 0);
    }

    public function test_invalid_calendar_date_is_rejected_by_shift_save(): void
    {
        $this->actingAs($this->createAdmin())->post('/grafik/2026-02-31/zapisz-zmiane', [
            'workers' => [],
            'is_draft' => '0',
        ])->assertSessionHasErrors('_route_date');
    }

    public function test_availability_flags_handle_frontend_on_and_explicit_zero(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Anna', 'Checkbox');
        $date = now()->addDay()->toDateString();

        $this->actingAs($admin)->postJson(route('planner.day.availability', $date), [
            'workers' => [
                'frontend' => [
                    'worker_id' => $worker->id,
                    'morning_shift' => 'on',
                    'afternoon_shift' => '0',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('worker_availability', [
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => false,
        ]);
    }

    public function test_invalid_availability_flag_and_duplicate_worker_are_rejected(): void
    {
        $worker = $this->createWorker('Anna', 'Walidacja');
        $date = now()->addDay()->toDateString();

        $this->actingAs($this->createAdmin())->postJson(route('planner.day.availability', $date), [
            'workers' => [
                'first' => ['worker_id' => $worker->id, 'morning_shift' => 'invalid'],
                'second' => ['worker_id' => (string) $worker->id, 'afternoon_shift' => '1'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'workers.first.morning_shift',
            'workers.second.worker_id',
        ]);

        $this->assertDatabaseCount('worker_availability', 0);
    }

    public function test_invalid_calendar_date_is_rejected_by_availability_save(): void
    {
        $this->actingAs($this->createAdmin())->postJson('/grafik/2026-02-31/dostepnosc-pracownika', [
            'workers' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('_route_date');
    }

    public function test_storing_one_hundred_assignments_uses_a_bounded_number_of_queries(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();
        $this->insertWorkersWithAvailability(100, $date);
        $workerIds = Worker::query()->orderBy('id')->pluck('id');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->post(route('planner.day.shift', $date), [
            'workers' => $workerIds->mapWithKeys(fn (int $workerId) => [
                "{$workerId}_morning" => [
                    'worker_id' => $workerId,
                    'shift_type' => 'morning',
                ],
            ])->all(),
            'is_draft' => '0',
        ]);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('worker_shifts', 100);
        $this->assertLessThanOrEqual(8, $queryCount, "Expected at most 8 queries, got {$queryCount}.");
    }

    public function test_storing_fifty_availability_records_uses_a_bounded_number_of_queries(): void
    {
        $admin = $this->createAdmin();
        $date = now()->addDay()->toDateString();
        $this->insertWorkersWithAvailability(50, $date);
        $workerIds = Worker::query()->orderBy('id')->pluck('id');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->postJson(route('planner.day.availability', $date), [
            'workers' => $workerIds->mapWithKeys(function (int $workerId, int $index): array {
                return ["worker_{$workerId}" => [
                    'worker_id' => $workerId,
                    'morning_shift' => $index % 2 === 0 ? '1' : null,
                    'afternoon_shift' => $index % 2 === 0 ? null : '1',
                ]];
            })->all(),
        ]);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('worker_availability', 50);
        $this->assertLessThanOrEqual(6, $queryCount, "Expected at most 6 queries, got {$queryCount}.");
    }

    public function test_sync_rechecks_availability_after_request_validation(): void
    {
        $worker = $this->createWorker('Race', 'Condition');
        $date = now()->addDay()->toDateString();
        $this->createAvailability($worker->id, $date);
        $entries = [[
            'worker_id' => $worker->id,
            'shift_type' => 'morning',
        ]];
        $validator = Validator::make([], []);

        WorkerAvailableForShift::validateBatch($entries, $date, $validator);
        $this->assertFalse($validator->fails());

        WorkerAvailability::query()
            ->where('worker_id', $worker->id)
            ->where('day', $date)
            ->delete();

        try {
            $this->app->make(PlannerDayShiftSyncService::class)->sync(
                $date,
                ['workers' => $entries],
                false,
            );
            $this->fail('Expected stale availability to be rejected inside the transaction.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workers', $exception->errors());
        }

        $this->assertDatabaseMissing('worker_shifts', [
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);
    }

    private function insertWorkersWithAvailability(int $count, string $date): void
    {
        $workers = [];

        for ($index = 1; $index <= $count; $index++) {
            $workers[] = [
                'first_name' => "Worker {$index}",
                'last_name' => 'Performance',
            ];
        }

        Worker::query()->insert($workers);

        WorkerAvailability::query()->insert(
            Worker::query()->orderBy('id')->pluck('id')->map(fn (int $workerId) => [
                'worker_id' => $workerId,
                'day' => $date,
                'morning_shift' => true,
                'afternoon_shift' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all(),
        );
    }
}
