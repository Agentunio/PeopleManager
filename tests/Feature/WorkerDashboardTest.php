<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Schedule;
use App\Models\ShiftStart;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkerUser(string $username = 'anna', string $firstName = 'Anna', string $lastName = 'Nowak'): User
    {
        $worker = Worker::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $user = User::create([
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'worker_id' => $worker->id,
        ]);

        $user->role = 'worker';
        $user->save();

        return $user;
    }

    public function test_worker_dashboard_displays_worker_name(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Anna');
        $response->assertSee('Nowak');
        $response->assertDontSee('Jan Kowalski');
    }

    public function test_worker_dashboard_requires_authentication(): void
    {
        $response = $this->get('/strefa-pracownika');

        $response->assertRedirect(route('login'));
    }

    public function test_schedule_inactive_when_disabled(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'disabled']);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('zapisy niedostępne');
        $response->assertSee('is-off');
    }

    public function test_schedule_active_with_signup(): void
    {
        $user = $this->createWorkerUser();
        $deadline = now()->addDay();
        $rangeStart = now()->addDays(3);
        $rangeEnd = now()->addDays(7);

        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => $deadline,
            'start_date' => $rangeStart,
            'end_date' => $rangeEnd,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dostępne do');
        $response->assertSee($deadline->format('d.m, H:i'));
        $response->assertSee($rangeStart->format('d.m'));
        $response->assertDontSee('is-off');
    }

    public function test_schedule_active_always_no_end_date(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'always']);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Zapisy otwarte');
        $response->assertDontSee('is-off');
    }

    public function test_schedule_inactive_when_no_schedule_exists(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('zapisy niedostępne');
    }

    public function test_dashboard_displays_worked_hours_and_salary(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->toDateString(),
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 480,
            'status' => 'worked',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->addDay()->toDateString(),
            'shift_type' => 'afternoon',
            'package_id' => $package->id,
            'minutes' => 300,
            'status' => 'worked',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('13h');
        $response->assertSeeText('390,00 zł');
    }

    public function test_dashboard_calendar_includes_future_shifts_without_counting_them_in_totals(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        foreach ([['2026-07-10', 60], ['2026-07-20', 120]] as [$day, $minutes]) {
            WorkerShift::create([
                'worker_id' => $user->worker->id,
                'day' => $day,
                'shift_type' => 'morning',
                'package_id' => $package->id,
                'minutes' => $minutes,
                'status' => 'worked',
            ]);
        }

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $this->assertSame(60, $response->viewData('stats')['totalMinutes']);
        $this->assertSame(120, $response->viewData('monthDays')['2026-07-20']['minutes']);
    }

    public function test_dashboard_shows_zero_when_no_shifts(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('0h');
        $response->assertSeeText('0,00 zł');
    }

    public function test_dashboard_excludes_absent_shifts_from_hours(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $package = Package::create(['name' => 'Standard', 'price' => 25.00]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->toDateString(),
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 480,
            'status' => 'worked',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->startOfMonth()->addDay()->toDateString(),
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 0,
            'status' => 'absent',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('8h');
        $response->assertSeeText('200,00 zł');
    }

    public function test_admin_cannot_access_worker_dashboard(): void
    {
        $user = User::create([
            'username' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $user->role = 'admin';
        $user->save();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(403);
    }

    public function test_worker_without_profile_gets_403(): void
    {
        $user = User::create([
            'username' => 'orphan',
            'email' => 'orphan@example.test',
            'password' => 'password',
        ]);

        $user->role = 'worker';
        $user->save();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(403);
    }

    public function test_dashboard_shows_next_shift(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->addDay()->toDateString(),
            'shift_type' => 'morning',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->addDay()->toDateString(),
            'shift_type' => 'afternoon',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('poranna');
        $response->assertSee('popołudniowa');
        $response->assertSee('start zmiany');
        $response->assertDontSee('Brak zaplanowanych zmian');
    }

    public function test_dashboard_shows_next_shift_start_time(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 08:00:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 630,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('10:30');
    }

    public function test_dashboard_block_label_uses_configured_shift_start_time(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 10:00:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = now()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        ShiftStart::create([
            'day' => $date,
            'shift_type' => 'morning',
            'start_time' => 630,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Godziny można wpisać po 10:30');
    }

    public function test_dashboard_keeps_today_as_next_when_configured_afternoon_start_not_passed(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 21:30:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        ShiftStart::create([
            'day' => $today,
            'shift_type' => 'afternoon',
            'start_time' => 1320,
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $today,
            'shift_type' => 'afternoon',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $tomorrow,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(Carbon::parse($today)->translatedFormat('j'));
        $response->assertDontSee(Carbon::parse($tomorrow)->translatedFormat('j').' ');
    }

    public function test_dashboard_does_not_show_started_morning_shift_as_upcoming(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 12:00:00'));
        $user = $this->createWorkerUser();

        WorkerShift::create([
            'worker_id' => $user->worker->id,
            'day' => now()->toDateString(),
            'shift_type' => 'morning',
        ]);

        $nextShift = $this->actingAs($user)
            ->get(route('worker.dashboard'))
            ->viewData('nextShift');

        $this->assertNull($nextShift);
    }

    public function test_dashboard_shows_only_not_started_shift_from_today(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 12:00:00'));
        $user = $this->createWorkerUser();

        foreach (['morning', 'afternoon'] as $shiftType) {
            WorkerShift::create([
                'worker_id' => $user->worker->id,
                'day' => now()->toDateString(),
                'shift_type' => $shiftType,
            ]);
        }

        $nextShift = $this->actingAs($user)
            ->get(route('worker.dashboard'))
            ->viewData('nextShift');

        $this->assertSame(['afternoon'], $nextShift['shifts']);
        $this->assertSame('popołudniowa', $nextShift['entries'][0]['label']);
    }

    public function test_dashboard_skips_today_when_default_afternoon_start_passed(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 21:30:00'));

        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $today,
            'shift_type' => 'afternoon',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $tomorrow,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(Carbon::parse($tomorrow)->translatedFormat('j'));
    }

    public function test_dashboard_shows_empty_state_when_no_shifts(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Brak zaplanowanych zmian');
    }

    public function test_dashboard_ignores_past_shifts(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => now()->subDay()->toDateString(),
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Brak zaplanowanych zmian');
    }

    public function test_dashboard_exposes_substitute_shift_for_hours_entry(): void
    {
        $this->travelTo(Carbon::parse('2026-04-29 12:00:00'));

        $user = $this->createWorkerUser();
        $absentWorker = Worker::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);
        $date = now()->toDateString();

        $absentShift = WorkerShift::create([
            'worker_id' => $absentWorker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
        ]);
        WorkerShift::create([
            'worker_id' => $user->worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'substituted_for_shift_id' => $absentShift->id,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));
        $lastShift = $response->viewData('lastShift');

        $response->assertOk();
        $this->assertNotNull($lastShift);
        $this->assertSame($date, $lastShift['date']);
        $this->assertArrayHasKey('morning', $lastShift['shifts']);
    }

    // --- Range filter: stats endpoint ---------------------------------------

    private function statsUrl(string $from, string $to): string
    {
        return route('worker.dashboard.stats', ['from' => $from, 'to' => $to]);
    }

    private function createShift(int $workerId, string $day, string $type, ?int $packageId, int $minutes, string $status = 'worked'): WorkerShift
    {
        return WorkerShift::create([
            'worker_id' => $workerId,
            'day' => $day,
            'shift_type' => $type,
            'package_id' => $packageId,
            'minutes' => $minutes,
            'status' => $status,
        ]);
    }

    public function test_stats_endpoint_returns_month_trend_for_range_starting_on_first(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);
        $previousEnd = now()->subMonthNoOverflow();

        // 240 zł w poprzednim miesiącu, 360 zł w bieżącym → +50%.
        $this->createShift($user->worker->id, $previousEnd->copy()->startOfMonth()->toDateString(), 'morning', $package->id, 480);
        $this->createShift($user->worker->id, now()->startOfMonth()->toDateString(), 'morning', $package->id, 720);

        $response = $this->actingAs($user)->getJson(
            $this->statsUrl(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
        );

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(50.0, $response->json('salaryTrend.percent'), 0.05);
        $this->assertTrue($response->json('salaryTrend.isPositive'));
        $this->assertSame(
            Str::lower($previousEnd->translatedFormat('F')),
            $response->json('salaryTrend.prev_month_label')
        );
    }

    public function test_stats_endpoint_compares_custom_range_with_preceding_span(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);
        $anchor = now()->startOfMonth()->addDays(9);

        // Zakres 10.-12. (360 zł) vs bezpośrednio poprzedzający 7.-9. (240 zł) → +50%.
        $this->createShift($user->worker->id, $anchor->copy()->toDateString(), 'morning', $package->id, 720);
        $this->createShift($user->worker->id, $anchor->copy()->subDays(3)->toDateString(), 'morning', $package->id, 480);

        $response = $this->actingAs($user)->getJson(
            $this->statsUrl($anchor->toDateString(), $anchor->copy()->addDays(2)->toDateString())
        );

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(50.0, $response->json('salaryTrend.percent'), 0.05);
        $this->assertSame('poprzedni okres', $response->json('salaryTrend.prev_month_label'));
    }

    public function test_stats_endpoint_returns_null_trend_without_previous_data(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        $this->createShift($user->worker->id, now()->startOfMonth()->toDateString(), 'morning', $package->id, 480);

        $response = $this->actingAs($user)->getJson(
            $this->statsUrl(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
        );

        $response->assertStatus(200);
        $this->assertNull($response->json('salaryTrend'));
    }

    public function test_stats_endpoint_returns_daily_breakdown(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);
        $day = now()->startOfMonth()->toDateString();

        $this->createShift($user->worker->id, $day, 'morning', $package->id, 480);

        $response = $this->actingAs($user)->getJson(
            $this->statsUrl(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
        );

        $response->assertStatus(200);
        $this->assertSame(480, $response->json("days.{$day}.minutes"));
        $this->assertEqualsWithDelta(240.0, $response->json("days.{$day}.salary"), 0.0001);
    }

    public function test_stats_endpoint_does_not_leak_other_workers_data(): void
    {
        $user = $this->createWorkerUser();
        $other = $this->createWorkerUser('bob', 'Bob', 'Inny');
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        $this->createShift($other->worker->id, now()->startOfMonth()->toDateString(), 'morning', $package->id, 480);

        $response = $this->actingAs($user)->getJson(
            $this->statsUrl(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
        );

        $response->assertStatus(200);
        $this->assertSame([], $response->json('days'));
    }

    public function test_stats_endpoint_returns_403_for_worker_without_profile(): void
    {
        $user = User::create([
            'username' => 'orphan_stats',
            'email' => 'orphan_stats@example.test',
            'password' => 'password',
        ]);
        $user->role = 'worker';
        $user->save();

        $response = $this->actingAs($user)->getJson(
            $this->statsUrl(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
        );

        $response->assertStatus(403);
    }

    public function test_stats_endpoint_rejects_reversed_and_missing_dates(): void
    {
        $user = $this->createWorkerUser();

        $this->actingAs($user)
            ->getJson($this->statsUrl('2026-05-10', '2026-05-01'))
            ->assertStatus(422);

        $this->actingAs($user)
            ->getJson(route('worker.dashboard.stats'))
            ->assertStatus(422);
    }

    public function test_stats_endpoint_rejects_span_over_cap(): void
    {
        $user = $this->createWorkerUser();

        $this->actingAs($user)
            ->getJson($this->statsUrl('2026-01-01', '2026-12-31'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    // --- Range filter: daily breakdown --------------------------------------

    public function test_daily_breakdown_sums_both_shifts_of_one_day(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);
        $day = now()->startOfMonth()->toDateString();

        $this->createShift($user->worker->id, $day, 'morning', $package->id, 480);
        $this->createShift($user->worker->id, $day, 'afternoon', $package->id, 300);

        $breakdown = app(WorkerStatsService::class)->getDailyBreakdown(
            $user->worker,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertSame(780, $breakdown[$day]['minutes']);
        $this->assertEqualsWithDelta(390.0, $breakdown[$day]['salary'], 0.0001);
    }

    /**
     * Absence must not add money or hours, but the day still has to be reported
     * so the calendar can mark it red ("nie był").
     */
    public function test_daily_breakdown_flags_absence_without_counting_it(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);
        $day = now()->startOfMonth()->toDateString();

        $this->createShift($user->worker->id, $day, 'morning', $package->id, 0, 'absent');

        $breakdown = app(WorkerStatsService::class)->getDailyBreakdown(
            $user->worker,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertTrue($breakdown[$day]['absent']);
        $this->assertSame(0, $breakdown[$day]['minutes']);
        $this->assertEqualsWithDelta(0.0, $breakdown[$day]['salary'], 0.0001);
    }

    public function test_daily_breakdown_marks_worked_day_as_not_absent(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);
        $day = now()->startOfMonth()->toDateString();

        $this->createShift($user->worker->id, $day, 'morning', $package->id, 480);

        $breakdown = app(WorkerStatsService::class)->getDailyBreakdown(
            $user->worker,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertFalse($breakdown[$day]['absent']);
        $this->assertSame(480, $breakdown[$day]['minutes']);
    }

    /**
     * Guards against per-day rounding: buildStats() rounds only the final sum,
     * so a breakdown rounded per day would drift by cents.
     */
    public function test_daily_breakdown_sum_matches_period_stats(): void
    {
        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Odd', 'price' => 33.33]);
        $start = now()->startOfMonth();

        $this->createShift($user->worker->id, $start->toDateString(), 'morning', $package->id, 50);
        $this->createShift($user->worker->id, $start->copy()->addDay()->toDateString(), 'morning', $package->id, 70);
        $this->createShift($user->worker->id, $start->copy()->addDays(2)->toDateString(), 'morning', $package->id, 110);

        $service = app(WorkerStatsService::class);
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $breakdown = $service->getDailyBreakdown($user->worker, $from, $to);
        $stats = $service->getStatsForWorker($user->worker, $from, $to);

        $sum = round(array_sum(array_column($breakdown, 'salary')), 2);

        $this->assertEqualsWithDelta($stats['salary'], $sum, 0.0001);
    }

    // --- Month-over-month salary trend --------------------------------------

    public function test_salary_trend_compares_same_span_of_previous_month(): void
    {
        $this->travelTo(Carbon::parse('2026-05-10 12:00:00'));

        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        $this->createShift($user->worker->id, '2026-04-05', 'morning', $package->id, 200);
        $this->createShift($user->worker->id, '2026-05-05', 'morning', $package->id, 400);

        $trend = $this->actingAs($user)->get(route('worker.dashboard'))->viewData('salaryTrend');

        $this->assertNotNull($trend);
        $this->assertEqualsWithDelta(100.0, $trend['percent'], 0.0001);
        $this->assertTrue($trend['isPositive']);
    }

    public function test_salary_trend_is_null_without_previous_month_data(): void
    {
        $this->travelTo(Carbon::parse('2026-05-10 12:00:00'));

        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        $this->createShift($user->worker->id, '2026-05-05', 'morning', $package->id, 400);

        $trend = $this->actingAs($user)->get(route('worker.dashboard'))->viewData('salaryTrend');

        $this->assertNull($trend);
    }

    /**
     * On the 31st a plain subMonth() overflows into the current month, which
     * would compare March against March instead of February.
     */
    public function test_salary_trend_does_not_overflow_on_month_end(): void
    {
        $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

        $user = $this->createWorkerUser();
        $package = Package::create(['name' => 'Standard', 'price' => 30.00]);

        $this->createShift($user->worker->id, '2026-02-10', 'morning', $package->id, 200);
        $this->createShift($user->worker->id, '2026-03-15', 'morning', $package->id, 400);

        $trend = $this->actingAs($user)->get(route('worker.dashboard'))->viewData('salaryTrend');

        $this->assertNotNull($trend);
        $this->assertEqualsWithDelta(100.0, $trend['percent'], 0.0001);
    }

    // --- Upcoming shifts ----------------------------------------------------

    public function test_upcoming_shifts_limit_counts_days_not_rows(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01 08:00:00'));

        $user = $this->createWorkerUser();
        $workerId = $user->worker->id;

        // Next shift, then four following days that each hold both shifts.
        foreach (['2026-05-02', '2026-05-03', '2026-05-04', '2026-05-05', '2026-05-06'] as $day) {
            $this->createShift($workerId, $day, 'morning', null, 0);
            $this->createShift($workerId, $day, 'afternoon', null, 0);
        }

        $upcoming = $this->actingAs($user)->get(route('worker.dashboard'))->viewData('upcomingShifts');

        $this->assertCount(3, $upcoming);
        $this->assertSame('03.05', $upcoming[0]['short_date']);
        $this->assertSame('05.05', $upcoming[2]['short_date']);
    }

    public function test_upcoming_shifts_empty_without_next_shift(): void
    {
        $user = $this->createWorkerUser();

        $upcoming = $this->actingAs($user)->get(route('worker.dashboard'))->viewData('upcomingShifts');

        $this->assertSame([], $upcoming);
    }

    public function test_upcoming_shifts_skip_drafts(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01 08:00:00'));

        $user = $this->createWorkerUser();
        $workerId = $user->worker->id;

        $this->createShift($workerId, '2026-05-02', 'morning', null, 0);
        $this->createShift($workerId, '2026-05-03', 'morning', null, 0)->update(['is_draft' => true]);
        $this->createShift($workerId, '2026-05-04', 'morning', null, 0);

        $upcoming = $this->actingAs($user)->get(route('worker.dashboard'))->viewData('upcomingShifts');

        $this->assertCount(1, $upcoming);
        $this->assertSame('04.05', $upcoming[0]['short_date']);
    }

    public function test_next_shift_in_days_is_counted_date_to_date(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01 23:30:00'));

        $user = $this->createWorkerUser();
        $this->createShift($user->worker->id, '2026-05-03', 'morning', null, 0);

        $nextShift = $this->actingAs($user)->get(route('worker.dashboard'))->viewData('nextShift');

        $this->assertSame(2, $nextShift['in_days']);
    }

    // --- Sign-up box data ---------------------------------------------------

    public function test_signup_array_exposes_deadline_and_range(): void
    {
        $schedule = Schedule::create([
            'type' => 'signup',
            'signup_deadline' => Carbon::parse('2026-05-22 23:59:00'),
            'start_date' => Carbon::parse('2026-05-25'),
            'end_date' => Carbon::parse('2026-06-07'),
        ]);

        $this->travelTo(Carbon::parse('2026-05-20 10:00:00'));

        $signup = $schedule->toSignupArray();

        $this->assertTrue($signup['is_active']);
        $this->assertSame('22.05, 23:59', $signup['deadline']);
        $this->assertSame('25.05', $signup['range_start']);
        $this->assertSame('07.06', $signup['range_end']);
        $this->assertSame(2, $signup['days_left']);
    }

    public function test_signup_array_for_always_schedule_has_no_dates(): void
    {
        $signup = Schedule::create(['type' => 'always'])->toSignupArray();

        $this->assertTrue($signup['is_active']);
        $this->assertNull($signup['deadline']);
        $this->assertNull($signup['range_start']);
        $this->assertNull($signup['days_left']);
    }

    public function test_signup_array_inactive_for_disabled_schedule(): void
    {
        $signup = Schedule::create(['type' => 'disabled'])->toSignupArray();

        $this->assertFalse($signup['is_active']);
    }
}
