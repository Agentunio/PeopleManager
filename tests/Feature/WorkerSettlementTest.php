<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkerSettlementTest extends TestCase
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

    private function createWorker(string $firstName, string $lastName, array $overrides = []): Worker
    {
        return Worker::create(array_merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_student' => false,
            'is_employed' => true,
        ], $overrides));
    }

    public function test_admin_receives_worker_summaries_and_selected_daily_breakdown(): void
    {
        $admin = $this->createAdmin();
        $selected = $this->createWorker('Anna', 'Kowalska');
        $other = $this->createWorker('Jan', 'Nowak');
        $package = Package::create(['name' => 'Standard', 'price' => 20]);

        WorkerShift::create([
            'worker_id' => $selected->id,
            'day' => '2026-07-01',
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 120,
            'status' => 'worked',
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $selected->id,
            'day' => '2026-07-01',
            'shift_type' => 'afternoon',
            'package_id' => $package->id,
            'minutes' => 60,
            'status' => 'worked',
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $selected->id,
            'day' => '2026-07-02',
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 0,
            'status' => 'absent',
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $selected->id,
            'day' => '2026-07-03',
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 600,
            'status' => 'worked',
            'is_draft' => true,
        ]);
        WorkerShift::create([
            'worker_id' => $other->id,
            'day' => '2026-07-01',
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 60,
            'status' => 'worked',
            'is_draft' => false,
        ]);

        $response = $this->actingAs($admin)->getJson(route('workers.settlements', [
            'workerId' => $selected->id,
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-03',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'workers')
            ->assertJsonPath('workers.0.id', $selected->id)
            ->assertJsonPath('workers.0.totalMinutes', 180)
            ->assertJsonPath('workers.0.salary', 60)
            ->assertJsonPath('selected.id', $selected->id)
            ->assertJsonPath('selected.totalMinutes', 180)
            ->assertJsonPath('selected.byShift.morning.totalMinutes', 120)
            ->assertJsonPath('selected.byShift.afternoon.totalMinutes', 60)
            ->assertJsonPath('selected.absences', 1)
            ->assertJsonCount(3, 'selected.days')
            ->assertJsonPath('selected.days.0.date', '2026-07-01')
            ->assertJsonPath('selected.days.0.morningMinutes', 120)
            ->assertJsonPath('selected.days.0.afternoonMinutes', 60)
            ->assertJsonPath('selected.days.1.absent', true)
            ->assertJsonPath('selected.days.2.morningMinutes', 0);
    }

    public function test_settlement_workers_are_paginated_ten_per_page(): void
    {
        $admin = $this->createAdmin();
        $workers = collect(range(1, 12))->map(fn (int $number): Worker => $this->createWorker(
            'Worker',
            sprintf('Person%02d', $number)
        ));

        $firstPage = $this->actingAs($admin)->getJson(route('workers.settlements', [
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-31',
            'page' => 1,
        ]));

        $firstPage
            ->assertOk()
            ->assertJsonCount(10, 'workers')
            ->assertJsonPath('workers.0.id', $workers[0]->id)
            ->assertJsonPath('workers.9.id', $workers[9]->id)
            ->assertJsonPath('pagination.currentPage', 1)
            ->assertJsonPath('pagination.lastPage', 2)
            ->assertJsonPath('pagination.perPage', 10)
            ->assertJsonPath('pagination.total', 12);

        $secondPage = $this->actingAs($admin)->getJson(route('workers.settlements', [
            'workerId' => $workers[10]->id,
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-31',
            'page' => 2,
        ]));

        $secondPage
            ->assertOk()
            ->assertJsonCount(2, 'workers')
            ->assertJsonPath('workers.0.id', $workers[10]->id)
            ->assertJsonPath('workers.1.id', $workers[11]->id)
            ->assertJsonPath('selected.id', $workers[10]->id)
            ->assertJsonPath('pagination.currentPage', 2)
            ->assertJsonPath('pagination.lastPage', 2);
    }

    public function test_settlement_search_filters_the_complete_worker_dataset(): void
    {
        $admin = $this->createAdmin();

        foreach (range(1, 11) as $number) {
            $this->createWorker('Worker', sprintf('Alpha%02d', $number));
        }

        $target = $this->createWorker('Alicja', 'Zielona');

        $response = $this->actingAs($admin)->getJson(route('workers.settlements', [
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-31',
            'searchWorker' => 'Alicja Zielona',
            'page' => 1,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'workers')
            ->assertJsonPath('workers.0.id', $target->id)
            ->assertJsonPath('selected.id', $target->id)
            ->assertJsonPath('pagination.currentPage', 1)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_settlement_range_is_validated_and_limited(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Anna', 'Kowalska');

        $this->actingAs($admin)->getJson(route('workers.settlements', [
            'workerId' => $worker->id,
            'dateFrom' => '2025-01-01',
            'dateTo' => '2026-07-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('dateTo');

        $this->actingAs($admin)->getJson(route('workers.settlements', [
            'workerId' => 999999,
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-31',
        ]))->assertUnprocessable()->assertJsonValidationErrors('workerId');

        $this->actingAs($admin)->getJson(route('workers.settlements', [
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-31',
            'page' => 0,
            'searchWorker' => str_repeat('a', 101),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['page', 'searchWorker']);
    }

    public function test_worker_and_guest_cannot_access_settlement_data(): void
    {
        $workerUser = User::create([
            'username' => 'settlement_worker',
            'email' => 'settlement-worker@example.test',
            'password' => 'password',
            'role' => 'worker',
        ]);
        $route = route('workers.settlements', [
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-31',
        ]);

        $this->actingAs($workerUser)->get($route)->assertForbidden();
    }

    public function test_guest_is_redirected_from_settlement_data(): void
    {
        $route = route('workers.settlements', [
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-31',
        ]);

        $this->get($route)->assertRedirect(route('login'));
    }

    public function test_settlements_tab_can_be_opened_directly(): void
    {
        $response = $this->actingAs($this->createAdmin())->get(route('workers.index', [
            'tab' => 'settlements',
        ]));

        $response
            ->assertOk()
            ->assertViewHas('activeTab', 'settlements')
            ->assertSee('data-active-tab=\'settlements\'', false);
    }

    public function test_worker_list_rejects_unknown_tab(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('workers.index', ['tab' => 'unknown']))
            ->assertSessionHasErrors('tab');
    }

    public function test_worker_list_supports_independent_employment_and_student_filters(): void
    {
        $admin = $this->createAdmin();
        $this->createWorker('Anna', 'EmployedStudent', [
            'is_student' => true,
            'is_employed' => true,
        ]);
        $this->createWorker('Jan', 'EmployedAdult', [
            'is_student' => false,
            'is_employed' => true,
        ]);
        $this->createWorker('Ola', 'FormerStudent', [
            'is_student' => true,
            'is_employed' => false,
        ]);

        $response = $this->actingAs($admin)->getJson(route('workers.index', [
            'filterEmployment' => '1',
            'filterStudent' => '1',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('filteredTotal', 1)
            ->assertJsonPath('totalWorkers', 3)
            ->assertSee('Anna EmployedStudent', false)
            ->assertDontSee('Jan EmployedAdult', false)
            ->assertDontSee('Ola FormerStudent', false);
    }

    public function test_worker_list_loads_only_fields_required_by_the_view_and_account_state(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker('Anna', 'Kowalska', ['address' => 'Toruń']);
        User::create([
            'username' => 'anna.kowalska',
            'email' => 'anna@example.test',
            'password' => 'password',
            'role' => 'worker',
            'worker_id' => $worker->id,
            'is_active' => false,
            'activation_token' => 'pending-token',
        ]);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($admin)->get(route('workers.index'));

        $response
            ->assertOk()
            ->assertSee('Anna Kowalska', false)
            ->assertSee('Oczekuje', false);

        $workerQuery = collect($queries)->first(
            fn (string $sql): bool => str_contains($sql, 'from "workers"') && str_contains($sql, 'order by')
        );
        $userQuery = collect($queries)->first(
            fn (string $sql): bool => str_contains($sql, 'from "users"') && str_contains($sql, 'worker_id')
        );

        $this->assertNotNull($workerQuery);
        $this->assertNotNull($userQuery);
        $this->assertStringNotContainsString('select *', strtolower($workerQuery));
        $this->assertStringContainsString('first_name', $workerQuery);
        $this->assertStringContainsString('address', $workerQuery);
        $this->assertStringNotContainsString('select *', strtolower($userQuery));
        $this->assertStringContainsString('activation_token', $userQuery);
        $this->assertStringContainsString('worker_id', $userQuery);
    }

    public function test_worker_json_escapes_script_breakout_payloads(): void
    {
        $payload = '</script><script>window.__worker_xss = true</script>';
        $this->createWorker($payload, 'Kowalska');

        $html = $this->actingAs($this->createAdmin())
            ->getJson(route('workers.index'))
            ->assertOk()
            ->json('html');

        $this->assertIsString($html);
        $this->assertSame(1, substr_count($html, '<script'));
        $this->assertStringNotContainsString('<script>window.__worker_xss = true</script>', $html);
        $this->assertStringContainsString(
            '\u003C\/script\u003E\u003Cscript\u003Ewindow.__worker_xss = true\u003C\/script\u003E',
            $html,
        );
    }
}
