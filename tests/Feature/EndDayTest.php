<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageShift;
use App\Models\ShiftStart;
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
            'email' => 'admin@example.test',
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

    public function test_settlement_page_receives_complete_design_data(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Anna', 'Nowak');
        $package = Package::create([
            'name' => 'Stawka testowa',
            'price' => 18.50,
            'is_default' => true,
        ]);
        $date = '2026-08-02';

        $this->shift($worker->id, $date, 'morning', [
            'package_id' => $package->id,
            'worker_from_time' => 7 * 60,
            'worker_to_time' => 13 * 60,
            'hours_source' => 'worker',
        ]);
        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => (7 * 60) + 15,
        ]);

        $response = $this->actingAs($admin)->get(route('planner.day.end-day', $date));

        $response->assertOk();
        $response->assertViewHas('packages', function (array $packages) use ($package): bool {
            return collect($packages)->contains(fn (array $item): bool => $item['id'] === $package->id
                && $item['name'] === 'Stawka testowa'
                && $item['price'] === 18.5
                && $item['isDefault'] === true
            );
        });
        $response->assertViewHas('shifts', function (array $shifts) use ($worker): bool {
            return $shifts['morning']['startTime'] === '07:15'
                && $shifts['morning']['workers'][0]['workerId'] === $worker->id
                && $shifts['morning']['workers'][0]['displayFrom'] === '07:00'
                && $shifts['morning']['workers'][0]['displayTo'] === '13:00'
                && $shifts['afternoon']['startTime'] === '21:00';
        });
    }

    public function test_admin_approved_range_is_stored_without_overwriting_worker_reported_hours(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Iwona', 'Kowalska');
        $date = '2026-08-02';

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
                    'from_minute' => 15,
                    'to_hour' => 17,
                    'to_minute' => 45,
                ],
            ],
        ])->assertRedirect();

        $shift = WorkerShift::where('worker_id', $worker->id)->firstOrFail();

        $this->assertSame(8 * 60, $shift->worker_from_time);
        $this->assertSame(14 * 60, $shift->worker_to_time);
        $this->assertSame((9 * 60) + 15, $shift->approved_from_time);
        $this->assertSame((17 * 60) + 45, $shift->approved_to_time);
        $this->assertSame(8 * 60 + 30, $shift->minutes);
        $this->assertSame('admin', $shift->hours_source);
    }

    public function test_marking_worker_absent_clears_approved_range(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Piotr', 'Zielinski');
        $date = '2026-08-02';

        $this->shift($worker->id, $date, 'morning', [
            'minutes' => 480,
            'approved_from_time' => 8 * 60,
            'approved_to_time' => 16 * 60,
            'hours_source' => 'admin',
        ]);

        $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$worker->id}_morning" => [
                    'id' => $worker->id,
                    'shift_type' => 'morning',
                    'status' => 'absent',
                ],
            ],
        ])->assertRedirect();

        $shift = WorkerShift::where('worker_id', $worker->id)->firstOrFail();

        $this->assertSame(0, $shift->minutes);
        $this->assertNull($shift->approved_from_time);
        $this->assertNull($shift->approved_to_time);
        $this->assertNull($shift->hours_source);
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
            'email' => 'bartek@example.test',
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

    public function test_substitute_without_hours_keeps_minutes_null_and_prevents_day_from_being_settled(): void
    {
        $admin = $this->createAdmin();
        $absentWorker = $this->createWorker('Michal', 'Lewandowski');
        $substitute = $this->createWorker('Mateusz', 'Kierschke');
        $package = Package::create(['name' => 'Test 10', 'price' => 10]);
        $date = '2026-04-25';

        $absentShift = $this->shift($absentWorker->id, $date, 'morning', [
            'status' => 'absent',
            'minutes' => 0,
        ]);

        PackageShift::create([
            'day' => $date,
            'shift_type' => 'morning',
            'packages_count' => 21,
            'package_id' => $package->id,
        ]);
        PackageShift::create([
            'day' => $date,
            'shift_type' => 'afternoon',
            'packages_count' => 421,
            'package_id' => $package->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$substitute->id}_morning" => [
                    'id' => $substitute->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => 1,
                    'substituted_for_shift_id' => $absentShift->id,
                    'from_hour' => '',
                    'from_minute' => '',
                    'to_hour' => '',
                    'to_minute' => '',
                ],
            ],
        ]);

        $response->assertRedirect();

        $substituteShift = WorkerShift::where('worker_id', $substitute->id)
            ->where('day', $date)
            ->where('shift_type', 'morning')
            ->first();

        $this->assertNull($substituteShift->minutes);
        $this->assertNull($substituteShift->hours_source);

        $calendar = $this->actingAs($admin)->get(route('planner.index', ['month' => '2026-04']));

        $this->assertNotContains($date, $calendar->viewData('settled'));
    }

    public function test_resaving_existing_substitute_without_hours_keeps_saved_minutes(): void
    {
        $admin = $this->createAdmin();
        $absentWorker = $this->createWorker('Michal', 'Lewandowski');
        $substitute = $this->createWorker('Mateusz', 'Kierschke');
        $package = Package::create(['name' => 'Test 10', 'price' => 10]);
        $date = '2026-04-25';

        $absentShift = $this->shift($absentWorker->id, $date, 'morning', [
            'status' => 'absent',
            'minutes' => 0,
        ]);

        $this->shift($substitute->id, $date, 'morning', [
            'status' => 'worked',
            'package_id' => $package->id,
            'minutes' => 300,
            'hours_source' => 'admin',
            'substituted_for_shift_id' => $absentShift->id,
        ]);

        $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$substitute->id}_morning" => [
                    'id' => $substitute->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => 1,
                    'substituted_for_shift_id' => $absentShift->id,
                    'package' => $package->id,
                ],
            ],
        ]);

        $substituteShift = WorkerShift::where('worker_id', $substitute->id)
            ->where('day', $date)
            ->where('shift_type', 'morning')
            ->first();

        $this->assertEquals(300, $substituteShift->minutes);
        $this->assertEquals('admin', $substituteShift->hours_source);
    }

    public function test_resaving_legacy_substitute_without_hours_clears_zero_minutes(): void
    {
        $admin = $this->createAdmin();
        $absentWorker = $this->createWorker('Michal', 'Lewandowski');
        $substitute = $this->createWorker('Mateusz', 'Kierschke');
        $date = '2026-04-25';

        $absentShift = $this->shift($absentWorker->id, $date, 'morning', [
            'status' => 'absent',
            'minutes' => 0,
        ]);

        $this->shift($substitute->id, $date, 'morning', [
            'status' => 'worked',
            'minutes' => 0,
            'substituted_for_shift_id' => $absentShift->id,
        ]);

        $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$substitute->id}_morning" => [
                    'id' => $substitute->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => 1,
                    'substituted_for_shift_id' => $absentShift->id,
                ],
            ],
        ]);

        $substituteShift = WorkerShift::where('worker_id', $substitute->id)
            ->where('day', $date)
            ->where('shift_type', 'morning')
            ->first();

        $this->assertNull($substituteShift->minutes);
        $this->assertNull($substituteShift->hours_source);
    }

    public function test_available_substitutes_exclude_unemployed_workers(): void
    {
        $admin = $this->createAdmin();
        $assignedWorker = $this->createWorker('Assigned', 'Worker');
        $employedWorker = $this->createWorker('Employed', 'Candidate');
        $unemployedWorker = $this->createWorker('Unemployed', 'Candidate');
        $unemployedWorker->update(['is_employed' => false]);
        $date = '2026-08-02';

        $this->shift($assignedWorker->id, $date, 'morning');

        $response = $this->actingAs($admin)->getJson(route('planner.day.substitution.available', [
            'date' => $date,
            'shift_type' => 'morning',
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $employedWorker->id]);
        $response->assertJsonMissing(['id' => $unemployedWorker->id]);
    }

    public function test_settlement_rejects_unemployed_substitute_submitted_outside_the_ui(): void
    {
        $admin = $this->createAdmin();
        $absentWorker = $this->createWorker('Absent', 'Worker');
        $unemployedWorker = $this->createWorker('Unemployed', 'Candidate');
        $unemployedWorker->update(['is_employed' => false]);
        $date = '2026-08-02';

        $absentShift = $this->shift($absentWorker->id, $date, 'morning', [
            'status' => 'absent',
            'minutes' => 0,
        ]);

        $workerKey = "{$unemployedWorker->id}_morning";
        $response = $this->actingAs($admin)->patchJson(route('planner.day.update', $date), [
            'workers' => [
                $workerKey => [
                    'id' => $unemployedWorker->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => true,
                    'substituted_for_shift_id' => $absentShift->id,
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors("workers.{$workerKey}.id");
        $this->assertDatabaseMissing('worker_shifts', [
            'worker_id' => $unemployedWorker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);
    }

    public function test_settlement_rejects_substitute_for_a_different_shift_type(): void
    {
        $admin = $this->createAdmin();
        $absentWorker = $this->createWorker('Absent', 'Worker');
        $substitute = $this->createWorker('Employed', 'Candidate');
        $date = '2026-08-02';

        $afternoonAbsence = $this->shift($absentWorker->id, $date, 'afternoon', [
            'status' => 'absent',
            'minutes' => 0,
        ]);

        $workerKey = "{$substitute->id}_morning";
        $response = $this->actingAs($admin)->patchJson(route('planner.day.update', $date), [
            'workers' => [
                $workerKey => [
                    'id' => $substitute->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => true,
                    'substituted_for_shift_id' => $afternoonAbsence->id,
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors("workers.{$workerKey}.substituted_for_shift_id");
        $this->assertDatabaseMissing('worker_shifts', [
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);
    }

    public function test_settlement_rejects_substitute_without_an_absent_shift(): void
    {
        $admin = $this->createAdmin();
        $substitute = $this->createWorker('Employed', 'Candidate');
        $date = '2026-08-02';
        $workerKey = "{$substitute->id}_morning";

        $response = $this->actingAs($admin)->patchJson(route('planner.day.update', $date), [
            'workers' => [
                $workerKey => [
                    'id' => $substitute->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => true,
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors("workers.{$workerKey}.substituted_for_shift_id");
        $this->assertDatabaseMissing('worker_shifts', [
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);
    }

    public function test_settlement_rejects_substitute_for_a_draft_absence(): void
    {
        $admin = $this->createAdmin();
        $absentWorker = $this->createWorker('Absent', 'Worker');
        $substitute = $this->createWorker('Employed', 'Candidate');
        $date = '2026-08-02';

        $draftAbsence = $this->shift($absentWorker->id, $date, 'morning', [
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => true,
        ]);

        $workerKey = "{$substitute->id}_morning";
        $response = $this->actingAs($admin)->patchJson(route('planner.day.update', $date), [
            'workers' => [
                $workerKey => [
                    'id' => $substitute->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => true,
                    'substituted_for_shift_id' => $draftAbsence->id,
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors("workers.{$workerKey}.substituted_for_shift_id");
        $this->assertDatabaseMissing('worker_shifts', [
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);
    }

    public function test_settlement_can_mark_absent_and_add_substitute_atomically(): void
    {
        $admin = $this->createAdmin();
        $originalWorker = $this->createWorker('Original', 'Worker');
        $substitute = $this->createWorker('Employed', 'Candidate');
        $date = '2026-08-02';
        $originalShift = $this->shift($originalWorker->id, $date, 'morning');

        $this->actingAs($admin)->patch(route('planner.day.update', $date), [
            'workers' => [
                "{$substitute->id}_morning" => [
                    'id' => $substitute->id,
                    'shift_type' => 'morning',
                    'status' => 'worked',
                    'is_substitute' => true,
                    'substituted_for_shift_id' => $originalShift->id,
                ],
                "{$originalWorker->id}_morning" => [
                    'id' => $originalWorker->id,
                    'shift_type' => 'morning',
                    'status' => 'absent',
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('worker_shifts', [
            'id' => $originalShift->id,
            'status' => 'absent',
        ]);
        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $substitute->id,
            'day' => $date,
            'shift_type' => 'morning',
            'substituted_for_shift_id' => $originalShift->id,
        ]);
    }

    public function test_worker_cannot_access_settlement_substitution_endpoints(): void
    {
        $workerUser = User::create([
            'username' => 'worker',
            'email' => 'worker@example.test',
            'password' => 'password',
        ]);
        $workerUser->role = 'worker';
        $workerUser->save();
        $date = '2026-08-02';

        $this->actingAs($workerUser)
            ->getJson(route('planner.day.substitution.available', [
                'date' => $date,
                'shift_type' => 'morning',
            ]))
            ->assertForbidden();

        $this->actingAs($workerUser)
            ->patchJson(route('planner.day.update', $date), ['workers' => []])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_settlement_substitution_endpoints(): void
    {
        $date = '2026-08-02';

        $this->get(route('planner.day.substitution.available', [
            'date' => $date,
            'shift_type' => 'morning',
        ]))->assertRedirect(route('login'));

        $this->patch(route('planner.day.update', $date), ['workers' => []])
            ->assertRedirect(route('login'));
    }
}
