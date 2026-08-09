<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerSelfHoursToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->travelTo(Carbon::parse('2026-05-06 14:00:00'));
    }

    private function createWorkerUser(): User
    {
        $worker = Worker::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
        ]);

        $user = User::create([
            'username' => 'anna',
            'email' => 'anna@example.test',
            'password' => 'password',
            'worker_id' => $worker->id,
        ]);
        $user->role = 'worker';
        $user->save();

        return $user;
    }

    private function pastDateInCurrentWeek(): string
    {
        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }

        return $yesterday->toDateString();
    }

    public function test_worker_can_submit_hours_when_enabled(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = $this->pastDateInCurrentWeek();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertOk();
    }

    public function test_worker_cannot_submit_hours_when_disabled(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = $this->pastDateInCurrentWeek();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, false);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Wpisywanie godzin zostalo wylaczone przez administratora']);
    }

    public function test_worker_cannot_submit_hours_when_setting_record_missing(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;
        $date = $this->pastDateInCurrentWeek();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        AppSetting::find(AppSetting::KEY_WORKER_SELF_HOURS)->delete();

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(403);
    }

    public function test_admin_endday_flow_unaffected_by_setting(): void
    {
        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, false);

        $admin = User::create([
            'username' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);
        $admin->role = 'admin';
        $admin->save();

        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = $this->pastDateInCurrentWeek();

        $shift = WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($admin)->patch(
            route('planner.day.update', $date),
            [
                'shifts' => [[
                    'id' => $shift->id,
                    'from_time' => '08:00',
                    'to_time' => '14:00',
                    'package_id' => null,
                ]],
            ]
        );

        $this->assertNotEquals(403, $response->status(), 'Admin EndDay update should not be blocked by toggle');
    }

    public function test_grafik_view_passes_flag_to_blade(): void
    {
        $user = $this->createWorkerUser();
        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, false);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('"workerSelfHoursEnabled":false', false);
    }

    public function test_dashboard_card_hidden_when_off_and_no_relevant_shifts(): void
    {
        $user = $this->createWorkerUser();
        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, false);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('enter-hours');
    }

    public function test_dashboard_card_visible_when_off_with_admin_hours(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;
        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, false);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $this->pastDateInCurrentWeek(),
            'shift_type' => 'morning',
            'hours_source' => 'admin',
            'minutes' => 360,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('dash-admin-info');
    }

    public function test_dashboard_card_visible_when_off_with_pending_worker_hours(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;
        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, false);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $this->pastDateInCurrentWeek(),
            'shift_type' => 'morning',
            'hours_source' => 'worker',
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
        ]);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('dash-saved-info');
        $response->assertDontSee('dash-edit-btn');
    }
}
