<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerScheduleModeTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'schedule_admin',
            'email' => 'schedule-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_create_admin_only_schedule_period(): void
    {
        $start = now()->addDays(2)->toDateString();
        $end = now()->addDays(9)->toDateString();

        $response = $this->actingAs($this->createAdmin())->post(route('planner.schedule.store'), [
            'type' => 'admin',
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $schedule = Schedule::findOrFail(1);
        $this->assertSame('admin', $schedule->type);
        $this->assertSame($start, $schedule->start_date->toDateString());
        $this->assertSame($end, $schedule->end_date->toDateString());
        $this->assertNull($schedule->signup_deadline);
        $this->assertFalse($schedule->isActive());
        $this->assertTrue($schedule->isAdminManaged());
    }

    public function test_admin_only_schedule_requires_valid_range(): void
    {
        $response = $this->actingAs($this->createAdmin())->post(route('planner.schedule.store'), [
            'type' => 'admin',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_always_schedule_can_be_limited_to_selected_range(): void
    {
        $start = now()->addDays(2)->toDateString();
        $end = now()->addDays(4)->toDateString();

        $this->actingAs($this->createAdmin())->post(route('planner.schedule.store'), [
            'type' => 'always',
            'start_date' => $start,
            'end_date' => $end,
        ])->assertSessionDoesntHaveErrors();

        $schedule = Schedule::findOrFail(1);
        $this->assertTrue($schedule->isActive());
        $this->assertTrue($schedule->isDateInSchedule(Carbon::parse($start)));
        $this->assertFalse($schedule->isDateInSchedule(Carbon::parse($end)->addDay()));
    }

    public function test_limited_always_schedule_becomes_inactive_after_its_end_date(): void
    {
        Carbon::setTestNow('2026-07-26 12:00:00');

        $schedule = Schedule::create([
            'id' => 1,
            'type' => 'always',
            'start_date' => '2026-07-12',
            'end_date' => '2026-07-19',
        ]);

        $this->assertFalse($schedule->isActive());
        $this->assertFalse($schedule->toAdminWindowArray()['allows_signup']);
    }

    public function test_incomplete_signup_schedule_is_inactive_and_safe_to_render(): void
    {
        $schedule = Schedule::create([
            'id' => 1,
            'type' => 'signup',
            'signup_deadline' => now()->addDay(),
        ]);

        $this->assertFalse($schedule->isActive());
        $this->assertSame(['is_active' => false, 'text' => ''], $schedule->toStatusArray());
    }

    public function test_planner_index_exposes_admin_only_window_data(): void
    {
        Schedule::create([
            'id' => 1,
            'type' => 'admin',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
        ]);

        $response = $this->actingAs($this->createAdmin())->get(route('planner.index', ['month' => '2026-07']));

        $response->assertOk()->assertViewHas('scheduleWindow', function (array $window): bool {
            return $window['type'] === 'admin'
                && $window['range_label'] === '06.07.2026 – 12.07.2026'
                && $window['allows_signup'] === false;
        });
    }

    public function test_current_schedule_uses_singleton_record_updated_by_admin(): void
    {
        $current = Schedule::create([
            'id' => 1,
            'type' => 'admin',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
        ]);
        Schedule::create([
            'id' => 2,
            'type' => 'disabled',
        ]);

        $this->assertTrue(Schedule::getCurrent()->is($current));
    }
}
