<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageShift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardShiftBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
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

    private function worker(string $first, string $last): Worker
    {
        return Worker::create([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => '500000000',
            'address' => 'addr',
            'date_of_birth' => '1990-01-01',
            'is_student' => false,
            'is_employed' => false,
        ]);
    }

    private function workedShift(
        Worker $worker,
        Package $package,
        string $day,
        string $shiftType = 'morning'
    ): WorkerShift {
        return WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $day,
            'shift_type' => $shiftType,
            'package_id' => $package->id,
            'minutes' => 60,
            'status' => 'worked',
            'is_draft' => false,
        ]);
    }

    public function test_data_endpoint_requires_auth(): void
    {
        $response = $this->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertStatus(401);
    }

    public function test_data_returns_byshift_split_for_revenue_cost_profit(): void
    {
        $rate = Package::create(['name' => 'Stawka 1', 'price' => 20]);
        $worker = $this->worker('Jan', 'Kowalski');

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'package_id' => $rate->id,
            'minutes' => 120,
            'status' => 'worked',
            'is_draft' => false,
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-10',
            'shift_type' => 'afternoon',
            'package_id' => $rate->id,
            'minutes' => 60,
            'status' => 'worked',
            'is_draft' => false,
        ]);

        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'packages_count' => 10,
            'package_id' => $rate->id,
        ]);
        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'afternoon',
            'packages_count' => 5,
            'package_id' => $rate->id,
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'totalRevenue',
            'totalCost',
            'totalProfit',
            'byShift' => [
                'morning' => ['revenue', 'cost', 'profit'],
                'afternoon' => ['revenue', 'cost', 'profit'],
            ],
            'workers' => [
                ['name', 'hours', 'salary', 'byShift' => [
                    'morning' => ['hours', 'salary', 'totalMinutes', 'absences', 'absentDays'],
                    'afternoon' => ['hours', 'salary', 'totalMinutes', 'absences', 'absentDays'],
                ]],
            ],
        ]);

        $json = $response->json();

        $this->assertEquals(200.0, $json['byShift']['morning']['revenue']);
        $this->assertEquals(100.0, $json['byShift']['afternoon']['revenue']);

        $this->assertEquals(40.0, $json['byShift']['morning']['cost']);
        $this->assertEquals(20.0, $json['byShift']['afternoon']['cost']);

        $this->assertEquals(160.0, $json['byShift']['morning']['profit']);
        $this->assertEquals(80.0, $json['byShift']['afternoon']['profit']);

        $this->assertEquals(300.0, $json['totalRevenue']);
        $this->assertEquals(60.0, $json['totalCost']);
        $this->assertEquals(240.0, $json['totalProfit']);

        $worker = $json['workers'][0];
        $this->assertEquals(40.0, $worker['byShift']['morning']['salary']);
        $this->assertEquals(20.0, $worker['byShift']['afternoon']['salary']);
    }

    public function test_data_endpoint_returns_changes_byshift_in_comparison(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 10]);
        $worker = $this->worker('Anna', 'Nowak');

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'package_id' => $rate->id,
            'minutes' => 60,
            'status' => 'worked',
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-03-10',
            'shift_type' => 'morning',
            'package_id' => $rate->id,
            'minutes' => 30,
            'status' => 'worked',
            'is_draft' => false,
        ]);

        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'packages_count' => 10,
            'package_id' => $rate->id,
        ]);
        PackageShift::create([
            'day' => '2026-03-10',
            'shift_type' => 'morning',
            'packages_count' => 5,
            'package_id' => $rate->id,
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'compare_start_date' => '2026-03-01',
            'compare_end_date' => '2026-03-31',
        ]));

        $response->assertOk();
        $json = $response->json();

        $this->assertArrayHasKey('changes', $json);
        $this->assertArrayHasKey('byShift', $json['changes']);
        $this->assertArrayHasKey('morning', $json['changes']['byShift']);
        $this->assertArrayHasKey('afternoon', $json['changes']['byShift']);

        $this->assertEquals(100.0, $json['changes']['byShift']['morning']['revenue']['percent']);
        $this->assertTrue($json['changes']['byShift']['morning']['revenue']['isPositive']);

        $this->assertNull($json['changes']['byShift']['afternoon']['revenue']);
    }

    public function test_data_excludes_draft_shifts_from_byshift_costs(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 10]);
        $worker = $this->worker('Jan', 'Kowalski');

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'package_id' => $rate->id,
            'minutes' => 600,
            'status' => 'worked',
            'is_draft' => true,
        ]);
        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-11',
            'shift_type' => 'afternoon',
            'package_id' => $rate->id,
            'minutes' => 60,
            'status' => 'worked',
            'is_draft' => false,
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();
        $json = $response->json();

        $this->assertEquals(0.0, $json['byShift']['morning']['cost']);
        $this->assertEquals(10.0, $json['byShift']['afternoon']['cost']);
    }

    public function test_data_paginates_worker_costs_without_limiting_dashboard_totals(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);

        foreach (range(1, 12) as $number) {
            $worker = $this->worker('Pracownik', sprintf('%02d', $number));
            $this->workedShift($worker, $rate, '2026-04-10');
        }

        $admin = $this->admin();
        $firstPage = $this->actingAs($admin)->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'page' => 1,
            'shift' => 'total',
        ]));

        $firstPage->assertOk()
            ->assertJsonCount(10, 'workers')
            ->assertJsonPath('workerPagination.currentPage', 1)
            ->assertJsonPath('workerPagination.lastPage', 2)
            ->assertJsonPath('workerPagination.perPage', 10)
            ->assertJsonPath('workerPagination.total', 12)
            ->assertJsonPath('totalCost', 240);

        $secondPage = $this->actingAs($admin)->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'page' => 2,
            'shift' => 'total',
        ]));

        $secondPage->assertOk()
            ->assertJsonCount(2, 'workers')
            ->assertJsonPath('workerPagination.currentPage', 2)
            ->assertJsonPath('workerPagination.total', 12)
            ->assertJsonPath('totalCost', 240);
    }

    public function test_data_paginates_workers_for_the_selected_shift(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);

        foreach (range(1, 11) as $number) {
            $worker = $this->worker('Rano', sprintf('%02d', $number));
            $this->workedShift($worker, $rate, '2026-04-10');
        }

        foreach (range(1, 3) as $number) {
            $worker = $this->worker('Popoludnie', sprintf('%02d', $number));
            $this->workedShift($worker, $rate, '2026-04-10', 'afternoon');
        }

        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'page' => 1,
            'shift' => 'afternoon',
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'workers')
            ->assertJsonPath('workerPagination.currentPage', 1)
            ->assertJsonPath('workerPagination.lastPage', 1)
            ->assertJsonPath('workerPagination.total', 3);

        foreach ($response->json('workers') as $worker) {
            $this->assertGreaterThan(0, $worker['byShift']['afternoon']['totalMinutes']);
        }
    }

    public function test_data_rejects_invalid_worker_pagination_parameters(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'page' => 0,
            'shift' => 'night',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['page', 'shift']);
    }

    public function test_data_rejects_range_over_span_limit(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2024-01-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_data_rejects_comparison_range_over_span_limit(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'compare_start_date' => '2023-01-01',
            'compare_end_date' => '2025-04-30',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['compare_end_date']);
    }

    public function test_data_rejects_page_over_limit(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'page' => 201,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['page']);
    }

    public function test_data_accepts_year_long_range(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2025-05-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();
    }
}
