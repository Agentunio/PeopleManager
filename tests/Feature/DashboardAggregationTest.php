<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageShift;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use App\Services\PackageStatsService;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the SQL-side aggregation of dashboard stats: worker totals, absences
 * with substitutes, package revenue and the multi-range cost query.
 */
class DashboardAggregationTest extends TestCase
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
            'is_student' => false,
            'is_employed' => true,
        ]);
    }

    private function shift(Worker $worker, Package $package, string $day, array $overrides = []): WorkerShift
    {
        return WorkerShift::create(array_merge([
            'worker_id' => $worker->id,
            'day' => $day,
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 60,
            'status' => 'worked',
            'is_draft' => false,
        ], $overrides));
    }

    // --- getStatsForWorkers ---------------------------------------------

    public function test_worker_stats_split_minutes_and_salary_per_shift(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 30]);
        $worker = $this->worker('Anna', 'Kowalska');

        $this->shift($worker, $rate, '2026-04-01', ['minutes' => 120]);
        $this->shift($worker, $rate, '2026-04-02', ['minutes' => 30]);
        $this->shift($worker, $rate, '2026-04-01', ['shift_type' => 'afternoon', 'minutes' => 90]);

        $stats = app(WorkerStatsService::class)->getStatsForWorkers(
            collect([$worker]),
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
        )->first()->stats;

        $this->assertSame(240, $stats['totalMinutes']);
        $this->assertSame('4h', $stats['hours']);
        $this->assertEqualsWithDelta(120.0, $stats['salary'], 0.001);

        $this->assertSame(150, $stats['byShift']['morning']['totalMinutes']);
        $this->assertEqualsWithDelta(75.0, $stats['byShift']['morning']['salary'], 0.001);
        $this->assertSame(90, $stats['byShift']['afternoon']['totalMinutes']);
        $this->assertEqualsWithDelta(45.0, $stats['byShift']['afternoon']['salary'], 0.001);
    }

    public function test_worker_stats_ignore_draft_shifts_and_absent_minutes(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);
        $worker = $this->worker('Jan', 'Nowak');

        $this->shift($worker, $rate, '2026-04-01', ['minutes' => 60]);
        $this->shift($worker, $rate, '2026-04-02', ['minutes' => 600, 'is_draft' => true]);
        $this->shift($worker, $rate, '2026-04-03', ['minutes' => 480, 'status' => 'absent']);

        $stats = app(WorkerStatsService::class)->getStatsForWorkers(
            collect([$worker]),
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
        )->first()->stats;

        $this->assertSame(60, $stats['totalMinutes']);
        $this->assertEqualsWithDelta(20.0, $stats['salary'], 0.001);
        $this->assertSame(1, $stats['absences']);
    }

    public function test_worker_stats_report_absent_days_with_substitute_name(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);
        $worker = $this->worker('Anna', 'Kowalska');
        $substitute = $this->worker('Piotr', 'Zastepca');

        $absentMorning = $this->shift($worker, $rate, '2026-04-05', ['minutes' => 0, 'status' => 'absent']);
        $this->shift($worker, $rate, '2026-04-05', [
            'shift_type' => 'afternoon',
            'minutes' => 0,
            'status' => 'absent',
        ]);
        $this->shift($worker, $rate, '2026-04-07', ['minutes' => 0, 'status' => 'absent']);

        $this->shift($substitute, $rate, '2026-04-05', [
            'minutes' => 120,
            'substituted_for_shift_id' => $absentMorning->id,
        ]);

        $stats = app(WorkerStatsService::class)->getStatsForWorkers(
            collect([$worker]),
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
        )->first()->stats;

        // Two absent shifts on 05.04 collapse into one absent day.
        $this->assertSame(2, $stats['absences']);
        $this->assertSame(
            [
                ['day' => '2026-04-05', 'substitute' => 'Piotr Zastepca'],
                ['day' => '2026-04-07', 'substitute' => null],
            ],
            $stats['absentDays']
        );

        // Morning keeps both absent days, afternoon only the one it has.
        $this->assertSame(2, $stats['byShift']['morning']['absences']);
        $this->assertSame('Piotr Zastepca', $stats['byShift']['morning']['absentDays'][0]['substitute']);
        $this->assertNull($stats['byShift']['morning']['absentDays'][1]['substitute']);
        $this->assertSame(1, $stats['byShift']['afternoon']['absences']);
        $this->assertNull($stats['byShift']['afternoon']['absentDays'][0]['substitute']);
    }

    public function test_worker_stats_match_the_single_worker_reference_implementation(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 23.5]);
        $worker = $this->worker('Anna', 'Kowalska');
        $substitute = $this->worker('Piotr', 'Zastepca');

        $absent = $this->shift($worker, $rate, '2026-04-05', ['minutes' => 0, 'status' => 'absent']);
        $this->shift($substitute, $rate, '2026-04-05', [
            'minutes' => 120,
            'substituted_for_shift_id' => $absent->id,
        ]);

        foreach (['2026-04-01', '2026-04-02', '2026-04-03'] as $index => $day) {
            $this->shift($worker, $rate, $day, ['minutes' => 55 + $index]);
            $this->shift($worker, $rate, $day, ['shift_type' => 'afternoon', 'minutes' => 125 + $index]);
        }

        $service = app(WorkerStatsService::class);
        $from = Carbon::parse('2026-04-01');
        $to = Carbon::parse('2026-04-30');

        $aggregated = $service->getStatsForWorkers(collect([$worker]), $from, $to)->first()->stats;
        $reference = $service->getStatsForWorker($worker, $from, $to);

        $this->assertSame($reference['hours'], $aggregated['hours']);
        $this->assertSame($reference['totalMinutes'], $aggregated['totalMinutes']);
        $this->assertEqualsWithDelta($reference['salary'], $aggregated['salary'], 0.001);
        $this->assertSame($reference['absences'], $aggregated['absences']);
        $this->assertSame($reference['absentDays'], $aggregated['absentDays']);
        $this->assertSame(
            $reference['byShift']['morning']['totalMinutes'],
            $aggregated['byShift']['morning']['totalMinutes']
        );
        $this->assertEqualsWithDelta(
            $reference['byShift']['afternoon']['salary'],
            $aggregated['byShift']['afternoon']['salary'],
            0.001
        );
    }

    public function test_worker_stats_scoped_to_a_single_shift_type(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);
        $worker = $this->worker('Anna', 'Kowalska');

        $this->shift($worker, $rate, '2026-04-01', ['minutes' => 60]);
        $this->shift($worker, $rate, '2026-04-01', ['shift_type' => 'afternoon', 'minutes' => 180]);

        $stats = app(WorkerStatsService::class)->getStatsForWorkers(
            collect([$worker]),
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
            'afternoon',
        )->first()->stats;

        $this->assertSame(180, $stats['totalMinutes']);
        $this->assertSame(0, $stats['byShift']['morning']['totalMinutes']);
        $this->assertSame(180, $stats['byShift']['afternoon']['totalMinutes']);
    }

    public function test_worker_without_shifts_gets_zeroed_stats(): void
    {
        $worker = $this->worker('Bez', 'Zmian');

        $stats = app(WorkerStatsService::class)->getStatsForWorkers(
            collect([$worker]),
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
        )->first()->stats;

        $this->assertSame(0, $stats['totalMinutes']);
        $this->assertSame('0h', $stats['hours']);
        $this->assertSame(0.0, $stats['salary']);
        $this->assertSame(0, $stats['absences']);
        $this->assertSame([], $stats['absentDays']);
        $this->assertSame([], $stats['byShift']['morning']['absentDays']);
    }

    // --- pagination ------------------------------------------------------

    public function test_dashboard_page_counts_only_workers_with_shifts_in_range(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);

        foreach (range(1, 3) as $number) {
            $worker = $this->worker('Pracownik', sprintf('%02d', $number));
            $this->shift($worker, $rate, '2026-04-10');
        }

        $this->worker('Poza', 'Zakresem');
        $outOfRange = $this->worker('Inny', 'Miesiac');
        $this->shift($outOfRange, $rate, '2026-03-10');

        $paginator = app(WorkerStatsService::class)->getDashboardWorkerPage(
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
            1,
            'total',
        );

        $this->assertSame(3, $paginator->total());
        $this->assertSame(1, $paginator->lastPage());
        $this->assertCount(3, $paginator->getCollection());
    }

    public function test_dashboard_page_clamps_page_over_last_page(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);

        foreach (range(1, 12) as $number) {
            $worker = $this->worker('Pracownik', sprintf('%02d', $number));
            $this->shift($worker, $rate, '2026-04-10');
        }

        $paginator = app(WorkerStatsService::class)->getDashboardWorkerPage(
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
            9,
            'total',
        );

        $this->assertSame(12, $paginator->total());
        $this->assertSame(2, $paginator->currentPage());
        $this->assertCount(2, $paginator->getCollection());
    }

    public function test_dashboard_page_handles_empty_range(): void
    {
        $paginator = app(WorkerStatsService::class)->getDashboardWorkerPage(
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
            1,
            'total',
        );

        $this->assertSame(0, $paginator->total());
        $this->assertCount(0, $paginator->getCollection());
    }

    // --- getCostByShiftForRanges ----------------------------------------

    public function test_cost_by_shift_for_ranges_keeps_periods_apart(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 60]);
        $worker = $this->worker('Anna', 'Kowalska');

        $this->shift($worker, $rate, '2026-04-10', ['minutes' => 60]);
        $this->shift($worker, $rate, '2026-04-11', ['shift_type' => 'afternoon', 'minutes' => 30]);
        $this->shift($worker, $rate, '2026-03-10', ['minutes' => 120]);
        // Outside both ranges.
        $this->shift($worker, $rate, '2026-02-10', ['minutes' => 600]);

        $costs = app(WorkerStatsService::class)->getCostByShiftForRanges([
            [Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30')],
            [Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31')],
        ]);

        $this->assertEqualsWithDelta(60.0, $costs[0]['morning'], 0.001);
        $this->assertEqualsWithDelta(30.0, $costs[0]['afternoon'], 0.001);
        $this->assertEqualsWithDelta(120.0, $costs[1]['morning'], 0.001);
        $this->assertEqualsWithDelta(0.0, $costs[1]['afternoon'], 0.001);
    }

    public function test_cost_by_shift_matches_multi_range_result(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 25]);
        $worker = $this->worker('Anna', 'Kowalska');

        $this->shift($worker, $rate, '2026-04-10', ['minutes' => 90]);
        $this->shift($worker, $rate, '2026-04-10', ['shift_type' => 'afternoon', 'minutes' => 45]);
        $this->shift($worker, $rate, '2026-04-12', ['minutes' => 0, 'status' => 'absent']);

        $service = app(WorkerStatsService::class);
        $from = Carbon::parse('2026-04-01');
        $to = Carbon::parse('2026-04-30');

        $this->assertSame(
            $service->getCostByShift($from, $to),
            $service->getCostByShiftForRanges([[$from, $to]])[0]
        );
    }

    // --- PackageStatsService ---------------------------------------------

    public function test_package_stats_aggregate_revenue_and_breakdown(): void
    {
        $standard = Package::create(['name' => 'Standard', 'price' => 10]);
        $premium = Package::create(['name' => 'Premium', 'price' => 20]);

        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'packages_count' => 10,
            'package_id' => $standard->id,
        ]);
        PackageShift::create([
            'day' => '2026-04-11',
            'shift_type' => 'morning',
            'packages_count' => 5,
            'package_id' => $standard->id,
        ]);
        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'packages_count' => 30,
            'package_id' => $premium->id,
        ]);
        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'afternoon',
            'packages_count' => 4,
            'package_id' => $premium->id,
        ]);
        // Outside the range.
        PackageShift::create([
            'day' => '2026-05-01',
            'shift_type' => 'morning',
            'packages_count' => 99,
            'package_id' => $premium->id,
        ]);

        $stats = app(PackageStatsService::class)->getStatsForPackages(
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
        );

        $this->assertSame(45, $stats['morning']['packages']);
        $this->assertEqualsWithDelta(750.0, $stats['morning']['revenue'], 0.001);
        $this->assertSame(
            [
                ['name' => 'Premium', 'packages' => 30],
                ['name' => 'Standard', 'packages' => 15],
            ],
            $stats['morning']['breakdown']
        );

        $this->assertSame(4, $stats['afternoon']['packages']);
        $this->assertEqualsWithDelta(80.0, $stats['afternoon']['revenue'], 0.001);

        $this->assertSame(49, $stats['total']['packages']);
        $this->assertEqualsWithDelta(830.0, $stats['total']['revenue'], 0.001);
    }

    public function test_package_stats_label_entries_without_rate(): void
    {
        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'packages_count' => 7,
            'package_id' => null,
        ]);

        $stats = app(PackageStatsService::class)->getStatsForPackages(
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
        );

        $this->assertSame(
            [['name' => 'Nieznana stawka', 'packages' => 7]],
            $stats['morning']['breakdown']
        );
        $this->assertEqualsWithDelta(0.0, $stats['morning']['revenue'], 0.001);
        $this->assertSame(7, $stats['total']['packages']);
    }

    public function test_package_stats_return_empty_shape_without_entries(): void
    {
        $stats = app(PackageStatsService::class)->getStatsForPackages(
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
        );

        $this->assertSame(0, $stats['morning']['packages']);
        $this->assertSame([], $stats['morning']['breakdown']);
        $this->assertSame(0.0, $stats['total']['revenue']);
    }

    // --- endpoint smoke test ---------------------------------------------

    public function test_dashboard_endpoint_totals_still_match_after_aggregation(): void
    {
        $rate = Package::create(['name' => 'Stawka', 'price' => 20]);
        $worker = $this->worker('Anna', 'Kowalska');

        $this->shift($worker, $rate, '2026-04-10', ['minutes' => 120]);
        $this->shift($worker, $rate, '2026-04-10', ['shift_type' => 'afternoon', 'minutes' => 60]);
        PackageShift::create([
            'day' => '2026-04-10',
            'shift_type' => 'morning',
            'packages_count' => 10,
            'package_id' => $rate->id,
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('dashboard.data', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'compare_start_date' => '2026-03-01',
            'compare_end_date' => '2026-03-31',
        ]));

        $response->assertOk()
            ->assertJsonPath('totalCost', 60)
            ->assertJsonPath('totalRevenue', 200)
            ->assertJsonPath('byShift.morning.cost', 40)
            ->assertJsonPath('byShift.afternoon.cost', 20)
            ->assertJsonPath('comparison.totalCost', 0)
            ->assertJsonPath('workers.0.hours', '3h')
            ->assertJsonPath('workerPagination.total', 1);
    }
}
