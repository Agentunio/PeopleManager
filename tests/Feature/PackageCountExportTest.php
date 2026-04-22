<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageShift;
use App\Models\User;
use App\Services\PackageCountExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageCountExportTest extends TestCase
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

    private function createWorkerUser(): User
    {
        $user = User::create([
            'username' => 'worker',
            'password' => 'password',
        ]);
        $user->role = 'worker';
        $user->save();

        return $user;
    }

    private function shift(string $day, string $type, int $count, int $packageId): PackageShift
    {
        return PackageShift::create([
            'day' => $day,
            'shift_type' => $type,
            'packages_count' => $count,
            'package_id' => $packageId,
        ]);
    }

    public function test_export_route_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.export.packages'), [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-07',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_export_route_forbidden_for_worker(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->post(route('dashboard.export.packages'), [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-07',
        ]);

        $response->assertForbidden();
    }

    public function test_export_validates_required_dates(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('dashboard.export.packages'), []);

        $response->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_export_validates_end_after_or_equal_start(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('dashboard.export.packages'), [
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-05',
        ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_service_groups_shifts_into_weeks_and_skips_rates_with_zero(): void
    {
        $rateA = Package::create(['name' => 'Stawka A', 'price' => 10]);
        $rateB = Package::create(['name' => 'Stawka B', 'price' => 15]);

        $this->shift('2026-04-06', 'morning', 5, $rateA->id);
        $this->shift('2026-04-06', 'afternoon', 3, $rateA->id);
        $this->shift('2026-04-07', 'morning', 2, $rateB->id);

        $this->shift('2026-04-13', 'morning', 4, $rateA->id);

        $service = app(PackageCountExportService::class);
        $data = $service->getExportData(
            Carbon::parse('2026-04-06'),
            Carbon::parse('2026-04-19')
        );

        $this->assertCount(2, $data['weeks']);

        $firstWeek = $data['weeks'][0];
        $this->assertSame(10, $firstWeek['weekTotal']);
        $this->assertCount(2, $firstWeek['rates']);

        $secondWeek = $data['weeks'][1];
        $this->assertSame(4, $secondWeek['weekTotal']);
        $this->assertCount(1, $secondWeek['rates']);
        $this->assertSame('Stawka A', $secondWeek['rates'][0]['name']);
    }

    public function test_service_respects_partial_week_range(): void
    {
        $rate = Package::create(['name' => 'Stawka X', 'price' => 10]);

        $this->shift('2026-04-06', 'morning', 1, $rate->id);
        $this->shift('2026-04-08', 'afternoon', 2, $rate->id);
        $this->shift('2026-04-12', 'morning', 99, $rate->id);

        $service = app(PackageCountExportService::class);
        $data = $service->getExportData(
            Carbon::parse('2026-04-07'),
            Carbon::parse('2026-04-09')
        );

        $this->assertCount(1, $data['weeks']);
        $week = $data['weeks'][0];
        $this->assertCount(3, $week['days']);
        $this->assertSame('2026-04-07', $week['days'][0]['date']);
        $this->assertSame('2026-04-09', $week['days'][2]['date']);
        $this->assertSame(2, $week['weekTotal']);
    }

    public function test_period_summary_aggregates_across_weeks_sorted_desc(): void
    {
        $rateA = Package::create(['name' => 'Stawka A', 'price' => 10]);
        $rateB = Package::create(['name' => 'Stawka B', 'price' => 15]);

        $this->shift('2026-04-06', 'morning', 3, $rateA->id);
        $this->shift('2026-04-13', 'morning', 7, $rateA->id);
        $this->shift('2026-04-07', 'afternoon', 20, $rateB->id);

        $service = app(PackageCountExportService::class);
        $data = $service->getExportData(
            Carbon::parse('2026-04-06'),
            Carbon::parse('2026-04-19')
        );

        $summary = $data['periodSummary'];
        $this->assertSame(30, $summary['grandTotal']);
        $this->assertCount(2, $summary['rows']);
        $this->assertSame('Stawka B', $summary['rows'][0]['name']);
        $this->assertSame(20, $summary['rows'][0]['total']);
        $this->assertSame('Stawka A', $summary['rows'][1]['name']);
        $this->assertSame(10, $summary['rows'][1]['total']);
    }

    public function test_empty_range_returns_no_weeks(): void
    {
        $service = app(PackageCountExportService::class);
        $data = $service->getExportData(
            Carbon::parse('2026-04-06'),
            Carbon::parse('2026-04-12')
        );

        $this->assertSame([], $data['weeks']);
        $this->assertSame([], $data['periodSummary']['rows']);
        $this->assertSame(0, $data['periodSummary']['grandTotal']);
    }

    public function test_generate_html_contains_rate_and_totals(): void
    {
        $rate = Package::create(['name' => 'Stawka Special', 'price' => 10]);

        $this->shift('2026-04-06', 'morning', 5, $rate->id);

        $service = app(PackageCountExportService::class);
        $data = $service->getExportData(
            Carbon::parse('2026-04-06'),
            Carbon::parse('2026-04-12')
        );

        $html = $service->generateHtml($data, '06.04.2026 - 12.04.2026');

        $this->assertStringContainsString('Stawka Special', $html);
        $this->assertStringContainsString('Podsumowanie okresu', $html);
        $this->assertStringContainsString('Paczki: 06.04.2026 - 12.04.2026', $html);
        $this->assertStringContainsString('>5<', $html);
    }
}
