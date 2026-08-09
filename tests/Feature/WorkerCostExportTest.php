<?php

namespace Tests\Feature;

use App\Contracts\HtmlExportRenderer;
use App\Models\Package;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use App\Services\WorkerStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeHtmlExportRenderer;
use Tests\TestCase;

class WorkerCostExportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'cost_admin',
            'email' => 'cost-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    public function test_export_rejects_ranges_longer_than_366_calendar_days(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('dashboard.export.costs'), [
                'start_date' => '2025-01-01',
                'end_date' => '2026-01-02',
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_cost_rows_are_aggregated_from_published_worked_shifts_only(): void
    {
        $worker = Worker::create(['first_name' => 'Anna', 'last_name' => 'Nowak']);
        $package = Package::create(['name' => 'Standard', 'price' => 30]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-01',
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 90,
            'status' => 'worked',
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-02',
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 480,
            'status' => 'absent',
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-04-03',
            'shift_type' => 'morning',
            'package_id' => $package->id,
            'minutes' => 600,
            'status' => 'worked',
            'is_draft' => true,
        ]);

        $rows = app(WorkerStatsService::class)->getCostExportRows(
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30'),
        );

        $this->assertCount(1, $rows);
        $this->assertSame(90, $rows->first()->stats['totalMinutes']);
        $this->assertSame('1h 30min', $rows->first()->stats['hours']);
        $this->assertSame(45.0, $rows->first()->stats['salary']);
    }

    public function test_valid_export_stays_an_immediate_pdf_download(): void
    {
        $fakeRenderer = new FakeHtmlExportRenderer;
        $this->app->instance(HtmlExportRenderer::class, $fakeRenderer);

        $response = $this->actingAs($this->createAdmin())
            ->post(route('dashboard.export.costs'), [
                'start_date' => '2025-01-01',
                'end_date' => '2026-01-01',
            ]);

        $response->assertDownload('pracownicy_2025-01-01_2026-01-01.pdf');
        $this->assertCount(1, $fakeRenderer->pdfCalls);
        $this->assertFalse($fakeRenderer->pdfCalls[0]['landscape']);

        @unlink($response->baseResponse->getFile()->getPathname());
    }

    public function test_expensive_exports_are_rate_limited_per_admin(): void
    {
        $fakeRenderer = new FakeHtmlExportRenderer;
        $this->app->instance(HtmlExportRenderer::class, $fakeRenderer);
        $admin = $this->createAdmin();

        foreach (range(1, 12) as $attempt) {
            $response = $this->actingAs($admin)->post(route('dashboard.export.costs'), [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-07',
            ]);

            $response->assertDownload('pracownicy_2026-04-01_2026-04-07.pdf');
            @unlink($response->baseResponse->getFile()->getPathname());
        }

        $this->actingAs($admin)
            ->post(route('dashboard.export.costs'), [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-07',
            ])
            ->assertStatus(429);

        $this->assertCount(12, $fakeRenderer->pdfCalls);
    }
}
