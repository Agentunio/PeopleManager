<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageShift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerShiftActionTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'planner_admin',
            'email' => 'planner-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    private function createWorker(string $firstName, string $lastName, bool $isEmployed = true): Worker
    {
        return Worker::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_employed' => $isEmployed,
        ]);
    }

    private function createShift(Worker $worker, string $date, string $type = 'morning', array $attributes = []): WorkerShift
    {
        return WorkerShift::create(array_merge([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => $type,
            'status' => 'worked',
            'is_draft' => false,
        ], $attributes));
    }

    public function test_admin_can_mark_worker_as_absent_from_planner_index(): void
    {
        $shift = $this->createShift(
            $this->createWorker('Jan', 'Kowalski'),
            '2026-07-06',
            attributes: [
                'minutes' => 480,
                'worker_from_time' => 540,
                'worker_to_time' => 1020,
                'approved_from_time' => 570,
                'approved_to_time' => 990,
                'hours_source' => 'worker',
            ],
        );

        $response = $this->actingAs($this->createAdmin())->patchJson(
            route('planner.day.shifts.status', ['date' => $shift->day, 'workerShift' => $shift]),
            ['status' => 'absent'],
        );

        $response->assertOk()->assertJsonStructure(['message', 'day_html']);
        $dayHtml = $response->json('day_html');
        $this->assertStringContainsString('data-shift-id="'.$shift->id.'"', $dayHtml);
        $this->assertStringNotContainsString(' data-status-url=', $dayHtml);
        $this->assertStringNotContainsString(' data-remove-url=', $dayHtml);
        $this->assertDatabaseHas('worker_shifts', [
            'id' => $shift->id,
            'status' => 'absent',
            'minutes' => 0,
            'package_id' => null,
            'worker_from_time' => null,
            'worker_to_time' => null,
            'approved_from_time' => null,
            'approved_to_time' => null,
            'hours_source' => null,
        ]);
    }

    public function test_restoring_worker_removes_existing_substitute_atomically(): void
    {
        $date = '2026-07-07';
        $original = $this->createShift(
            $this->createWorker('Anna', 'Nowak'),
            $date,
            attributes: ['status' => 'absent', 'minutes' => 0],
        );
        $substitute = $this->createShift(
            $this->createWorker('Piotr', 'Zielinski'),
            $date,
            attributes: ['substituted_for_shift_id' => $original->id],
        );

        $response = $this->actingAs($this->createAdmin())->patchJson(
            route('planner.day.shifts.status', ['date' => $date, 'workerShift' => $original]),
            ['status' => 'worked'],
        );

        $response->assertOk();
        $this->assertDatabaseHas('worker_shifts', [
            'id' => $original->id,
            'status' => 'worked',
            'minutes' => null,
        ]);
        $this->assertDatabaseMissing('worker_shifts', ['id' => $substitute->id]);
    }

    public function test_substitute_candidates_exclude_unemployed_and_already_assigned_workers(): void
    {
        $date = '2026-07-08';
        $original = $this->createShift(
            $this->createWorker('Jan', 'Nieobecny'),
            $date,
            attributes: ['status' => 'absent', 'minutes' => 0],
        );
        $available = $this->createWorker('Alicja', 'Dostepna');
        $assigned = $this->createWorker('Beata', 'Przypisana');
        $this->createWorker('Celina', 'Niepracujaca', false);
        $this->createShift($assigned, $date);
        WorkerAvailability::create([
            'worker_id' => $available->id,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => false,
        ]);

        $response = $this->actingAs($this->createAdmin())->getJson(
            route('planner.day.shifts.substitutes.index', ['date' => $date, 'workerShift' => $original]),
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id)
            ->assertJsonPath('data.0.is_available', true);
    }

    public function test_admin_can_assign_only_one_substitute_for_absent_shift(): void
    {
        $date = '2026-07-09';
        $original = $this->createShift(
            $this->createWorker('Jan', 'Nieobecny'),
            $date,
            attributes: ['status' => 'absent', 'minutes' => 0, 'is_draft' => true],
        );
        $firstCandidate = $this->createWorker('Alicja', 'Pierwsza');
        $secondCandidate = $this->createWorker('Beata', 'Druga');
        $admin = $this->createAdmin();
        $route = route('planner.day.shifts.substitutes.store', [
            'date' => $date,
            'workerShift' => $original,
        ]);

        $this->actingAs($admin)->postJson($route, ['worker_id' => $firstCandidate->id])
            ->assertOk()
            ->assertJsonStructure(['message', 'day_html']);

        $this->assertDatabaseHas('worker_shifts', [
            'worker_id' => $firstCandidate->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'substituted_for_shift_id' => $original->id,
            'is_draft' => true,
        ]);

        $this->actingAs($admin)->postJson($route, ['worker_id' => $secondCandidate->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('worker_id');
    }

    public function test_substitute_must_be_employed_and_not_assigned_to_same_shift(): void
    {
        $date = '2026-07-10';
        $original = $this->createShift(
            $this->createWorker('Jan', 'Nieobecny'),
            $date,
            attributes: ['status' => 'absent', 'minutes' => 0],
        );
        $assigned = $this->createWorker('Alicja', 'Przypisana');
        $unemployed = $this->createWorker('Beata', 'Niepracujaca', false);
        $this->createShift($assigned, $date);
        $admin = $this->createAdmin();
        $route = route('planner.day.shifts.substitutes.store', [
            'date' => $date,
            'workerShift' => $original,
        ]);

        $this->actingAs($admin)->postJson($route, ['worker_id' => $assigned->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('worker_id');

        $this->actingAs($admin)->postJson($route, ['worker_id' => $unemployed->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('worker_id');
    }

    public function test_removing_original_shift_also_removes_its_substitute(): void
    {
        $date = '2026-07-11';
        $original = $this->createShift(
            $this->createWorker('Jan', 'Oryginalny'),
            $date,
            attributes: ['status' => 'absent', 'minutes' => 0],
        );
        $substitute = $this->createShift(
            $this->createWorker('Alicja', 'Zastepcza'),
            $date,
            attributes: ['substituted_for_shift_id' => $original->id],
        );

        $this->actingAs($this->createAdmin())->deleteJson(
            route('planner.day.shifts.destroy', ['date' => $date, 'workerShift' => $original]),
        )->assertOk();

        $this->assertDatabaseMissing('worker_shifts', ['id' => $original->id]);
        $this->assertDatabaseMissing('worker_shifts', ['id' => $substitute->id]);
    }

    public function test_shift_action_rejects_route_date_mismatch(): void
    {
        $shift = $this->createShift($this->createWorker('Jan', 'Kowalski'), '2026-07-12');

        $this->actingAs($this->createAdmin())->patchJson(
            route('planner.day.shifts.status', ['date' => '2026-07-13', 'workerShift' => $shift]),
            ['status' => 'absent'],
        )->assertNotFound();
    }

    public function test_shift_actions_reject_settled_day(): void
    {
        $date = '2026-07-14';
        $package = Package::create(['name' => 'Standard', 'price' => 1]);
        $shift = $this->createShift(
            $this->createWorker('Jan', 'Kowalski'),
            $date,
            attributes: ['minutes' => 480],
        );
        foreach (['morning', 'afternoon'] as $type) {
            PackageShift::create([
                'day' => $date,
                'shift_type' => $type,
                'package_id' => $package->id,
                'packages_count' => 1,
            ]);
        }

        $this->actingAs($this->createAdmin())->patchJson(
            route('planner.day.shifts.status', ['date' => $date, 'workerShift' => $shift]),
            ['status' => 'absent'],
        )->assertUnprocessable()->assertJsonValidationErrors('shift');
    }

    public function test_worker_cannot_use_planner_shift_actions(): void
    {
        $shift = $this->createShift($this->createWorker('Jan', 'Kowalski'), '2026-07-15');
        $workerUser = User::create([
            'username' => 'worker_user',
            'email' => 'worker-user@example.test',
            'password' => 'password',
            'role' => 'worker',
        ]);

        $this->actingAs($workerUser)->patchJson(
            route('planner.day.shifts.status', ['date' => $shift->day, 'workerShift' => $shift]),
            ['status' => 'absent'],
        )->assertForbidden();

        $this->actingAs($workerUser)->get(
            route('planner.day.shifts.substitutes.index', ['date' => $shift->day, 'workerShift' => $shift]),
        )->assertForbidden();

        $this->actingAs($workerUser)->post(
            route('planner.day.shifts.substitutes.store', ['date' => $shift->day, 'workerShift' => $shift]),
            ['worker_id' => $shift->worker_id],
        )->assertForbidden();

        $this->actingAs($workerUser)->delete(
            route('planner.day.shifts.destroy', ['date' => $shift->day, 'workerShift' => $shift]),
        )->assertForbidden();
    }

    public function test_guest_is_redirected_from_planner_shift_actions(): void
    {
        $shift = $this->createShift($this->createWorker('Jan', 'Kowalski'), '2026-07-16');

        $this->get(
            route('planner.day.shifts.substitutes.index', ['date' => $shift->day, 'workerShift' => $shift]),
        )->assertRedirect(route('login'));

        $this->post(
            route('planner.day.shifts.substitutes.store', ['date' => $shift->day, 'workerShift' => $shift]),
            ['worker_id' => $shift->worker_id],
        )->assertRedirect(route('login'));

        $this->delete(
            route('planner.day.shifts.destroy', ['date' => $shift->day, 'workerShift' => $shift]),
        )->assertRedirect(route('login'));
    }
}
