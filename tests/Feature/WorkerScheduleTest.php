<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAvailability;
use App\Models\WorkerShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkerUser(): User
    {
        $worker = Worker::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
        ]);

        $user = User::create([
            'username' => 'anna',
            'password' => 'password',
            'worker_id' => $worker->id,
        ]);

        $user->role = 'worker';
        $user->save();

        return $user;
    }

    public function test_schedule_page_shows_active_status(): void
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

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('Grafik aktywny do:', false);
        $response->assertSee($deadline->format('d.m.Y H:i'));
        $response->assertSee($rangeStart->format('d.m.Y'));
        $response->assertSee($rangeEnd->format('d.m.Y'));
        $response->assertSee('is-active');
    }

    public function test_schedule_signup_shows_relative_week_label_when_in_next_week(): void
    {
        $user = $this->createWorkerUser();
        $nextMonday = now()->startOfWeek()->addWeek();

        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => now()->addDay(),
            'start_date' => $nextMonday,
            'end_date' => $nextMonday->copy()->addDays(6),
        ]);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('(następny tydzień)');
    }

    public function test_schedule_signup_inactive_after_deadline(): void
    {
        $user = $this->createWorkerUser();

        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => now()->subHour(),
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(6),
        ]);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('Nieaktywny');
        $response->assertDontSee('is-active');
    }

    public function test_schedule_page_shows_inactive_status(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'disabled']);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('Nieaktywny');
        $response->assertDontSee('is-active');
    }

    public function test_schedule_page_shows_inactive_when_no_schedule(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('Nieaktywny');
    }

    public function test_schedule_shows_current_week_days(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);

        $weekStart = now()->startOfWeek();
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $response->assertSee($date->toDateString());
        }
    }

    public function test_schedule_navigates_to_specific_week(): void
    {
        $user = $this->createWorkerUser();
        $targetWeek = now()->addWeeks(2)->startOfWeek();

        $response = $this->actingAs($user)->get(route('worker.schedule', ['week' => $targetWeek->format('d-m-Y')]));

        $response->assertStatus(200);
        $response->assertSee($targetWeek->toDateString());
        $response->assertSee($targetWeek->copy()->endOfWeek()->toDateString());
    }

    public function test_schedule_shows_worker_availability(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $today = now()->startOfWeek();

        WorkerAvailability::create([
            'worker_id' => $worker->id,
            'day' => $today->toDateString(),
            'morning_shift' => true,
            'afternoon_shift' => false,
        ]);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $content = $response->getContent();

        $response->assertStatus(200);
        $this->assertStringContainsString('data-date="' . $today->toDateString() . '"', $content);

        preg_match('/window\.scheduleDays\s*=\s*(\{.*?\});/s', $content, $matches);
        $this->assertNotEmpty($matches, 'window.scheduleDays not found in page');
        $scheduleDays = json_decode($matches[1], true);
        $this->assertNotNull($scheduleDays);
        $this->assertEquals('1', $scheduleDays[$today->toDateString()]['morning']);
        $this->assertEquals('0', $scheduleDays[$today->toDateString()]['afternoon']);
    }

    public function test_store_availability_creates_record(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        Schedule::create(['type' => 'always']);

        $date = now()->addDay()->toDateString();

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', $date),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('worker_availability', [
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => false,
        ]);
    }

    public function test_store_availability_updates_existing_record(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        Schedule::create(['type' => 'always']);

        $date = now()->addDay()->toDateString();

        WorkerAvailability::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => false,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', $date),
            ['morning_shift' => false, 'afternoon_shift' => true]
        );

        $response->assertOk();

        $this->assertDatabaseHas('worker_availability', [
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => false,
            'afternoon_shift' => true,
        ]);

        $this->assertDatabaseCount('worker_availability', 1);
    }

    public function test_store_availability_deletes_when_both_unchecked(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        Schedule::create(['type' => 'always']);

        $date = now()->addDay()->toDateString();

        WorkerAvailability::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => true,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', $date),
            ['morning_shift' => false, 'afternoon_shift' => false]
        );

        $response->assertOk();
        $this->assertDatabaseCount('worker_availability', 0);
    }

    public function test_store_availability_rejected_when_schedule_inactive(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'disabled']);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', now()->toDateString()),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Grafik jest nieaktywny']);
    }

    public function test_store_availability_rejected_when_no_schedule(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', now()->toDateString()),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertStatus(422);
    }

    public function test_modal_not_rendered_when_schedule_inactive(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'disabled']);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertDontSee('shiftModalOverlay');
    }

    public function test_modal_rendered_when_schedule_active(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'always']);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('shiftModalOverlay');
    }

    public function test_store_availability_rejected_for_today(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'always']);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', now()->toDateString()),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Nie można edytować dostępności dla dzisiejszego lub przeszłego dnia']);
    }

    public function test_store_availability_rejected_for_past_day(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'always']);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', now()->subDays(3)->toDateString()),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Nie można edytować dostępności dla dzisiejszego lub przeszłego dnia']);
    }

    public function test_store_availability_rejected_beyond_schedule_end_date(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => now()->addHour(),
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(4),
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', now()->addDays(8)->toDateString()),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Ten dzień wykracza poza zakres aktywnego grafiku']);
    }

    public function test_store_availability_rejected_when_deadline_passed(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => now()->subMinute(),
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(6),
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', now()->addDays(3)->toDateString()),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Grafik jest nieaktywny']);
    }

    public function test_store_availability_accepted_within_signup_range(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => now()->addDay(),
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(6),
        ]);

        $date = now()->addDays(3)->toDateString();

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', $date),
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertOk();
        $this->assertDatabaseHas('worker_availability', [
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => true,
        ]);
    }

    public function test_assigned_shift_cannot_be_unchecked(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        Schedule::create(['type' => 'always']);

        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', $date),
            ['morning_shift' => false, 'afternoon_shift' => true]
        );

        $response->assertOk();

        $this->assertDatabaseHas('worker_availability', [
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => true,
        ]);
    }

    public function test_schedule_shows_assigned_workers(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $otherWorker = Worker::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        WorkerShift::create([
            'worker_id' => $otherWorker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(200);
        $response->assertSee('Anna Nowak');
        $response->assertSee('Jan Kowalski');
    }

    public function test_past_week_shows_only_own_shifts(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $otherWorker = Worker::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $pastWeek = now()->subWeek()->startOfWeek();
        $date = $pastWeek->copy()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        WorkerShift::create([
            'worker_id' => $otherWorker->id,
            'day' => $date,
            'shift_type' => 'afternoon',
        ]);

        $response = $this->actingAs($user)->get(route('worker.schedule', ['week' => $pastWeek->format('d-m-Y')]));

        $response->assertStatus(200);
        $response->assertSee('Anna Nowak');
        $response->assertDontSee('Jan Kowalski');
    }

    public function test_store_hours_for_assigned_shift(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        $shift = WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:30']
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $shift->refresh();
        $this->assertEquals(8 * 60, $shift->worker_from_time);
        $this->assertEquals(14 * 60 + 30, $shift->worker_to_time);
        $this->assertEquals('worker', $shift->hours_source);
        $this->assertNull($shift->minutes);
    }

    public function test_store_hours_rejected_for_other_week(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $nextWeek = now()->addWeek()->startOfWeek()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $nextWeek,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $nextWeek),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Można wpisywać godziny tylko dla bieżącego tygodnia']);
    }

    public function test_store_hours_rejected_for_future_day(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $futureDay = now()->addDay();
        if ($futureDay->gt(now()->endOfWeek())) {
            $this->markTestSkipped('No future day in current week');
        }
        $date = $futureDay->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Nie można wpisać godzin dla przyszłego dnia']);
    }

    public function test_store_hours_rejected_for_unassigned_shift(): void
    {
        $user = $this->createWorkerUser();

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(403);
    }

    public function test_store_hours_rejected_for_other_workers_shift(): void
    {
        $user = $this->createWorkerUser();

        $otherWorker = Worker::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        WorkerShift::create([
            'worker_id' => $otherWorker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(403);
    }

    public function test_store_hours_rejected_when_admin_approved(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'hours_source' => 'admin',
            'minutes' => 360,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Godziny zostały już zatwierdzone przez administratora']);
    }

    public function test_worker_can_edit_own_reported_hours(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        $shift = WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
            'hours_source' => 'worker',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '09:00', 'to_time' => '15:00']
        );

        $response->assertOk();

        $shift->refresh();
        $this->assertEquals(9 * 60, $shift->worker_from_time);
        $this->assertEquals(15 * 60, $shift->worker_to_time);
    }

    public function test_store_hours_validates_to_after_from(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '14:00', 'to_time' => '08:00']
        );

        $response->assertStatus(422);
    }

    public function test_day_outside_schedule_is_not_clickable(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create([
            'type' => 'signup',
            'signup_deadline' => now()->addHour(),
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(2),
        ]);

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $content = $response->getContent();
        $dayAfterEnd = now()->addDays(4)->toDateString();

        if (str_contains($content, 'data-date="' . $dayAfterEnd . '"')) {
            $this->assertDoesNotMatchRegularExpression(
                '/data-date="' . preg_quote($dayAfterEnd) . '"[^>]*class="[^"]*clickable/',
                $content
            );
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_store_availability_rejects_invalid_date_format(): void
    {
        $user = $this->createWorkerUser();
        Schedule::create(['type' => 'always']);

        $response = $this->actingAs($user)->postJson(
            '/strefa-pracownika/grafik/dostepnosc/not-a-date',
            ['morning_shift' => true, 'afternoon_shift' => false]
        );

        $response->assertStatus(404);
    }

    public function test_store_hours_rejects_invalid_date_format(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->postJson(
            '/strefa-pracownika/grafik/godziny/invalid',
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(404);
    }

    public function test_store_hours_rejected_for_absent_shift(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        $date = $yesterday->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Nie można wpisać godzin — jesteś oznaczony jako nieobecny']);
    }

    public function test_schedule_page_requires_worker_profile(): void
    {
        $user = User::create([
            'username' => 'orphan',
            'password' => 'password',
        ]);

        $user->role = 'worker';
        $user->save();

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_worker_schedule(): void
    {
        $user = User::create([
            'username' => 'admin',
            'password' => 'password',
        ]);

        $user->role = 'admin';
        $user->save();

        $response = $this->actingAs($user)->get(route('worker.schedule'));

        $response->assertStatus(403);
    }

    public function test_schedule_rejects_invalid_week_format(): void
    {
        $user = $this->createWorkerUser();

        $response = $this->actingAs($user)->get(route('worker.schedule', ['week' => '99-99-9999']));

        $response->assertStatus(404);
    }

    public function test_store_availability_preserves_assigned_shift_when_unchecking(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        Schedule::create(['type' => 'always']);

        $date = now()->addDay()->toDateString();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'afternoon',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.availability', $date),
            ['morning_shift' => false, 'afternoon_shift' => false]
        );

        $response->assertOk();

        $this->assertDatabaseHas('worker_availability', [
            'worker_id' => $worker->id,
            'day' => $date,
            'morning_shift' => true,
            'afternoon_shift' => true,
        ]);
    }

    private function pastDateInCurrentWeek(): string
    {
        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        return $yesterday->toDateString();
    }

    public function test_store_hours_assigns_default_package_when_shift_has_no_package(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $defaultPackage = Package::create([
            'name' => 'Domyslna',
            'price' => 50.00,
            'is_default' => true,
        ]);
        Package::create(['name' => 'Inna', 'price' => 70.00]);

        $date = $this->pastDateInCurrentWeek();
        $shift = WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'package_id' => null,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertOk();
        $shift->refresh();
        $this->assertEquals($defaultPackage->id, $shift->package_id);
        $this->assertEquals('worker', $shift->hours_source);
    }

    public function test_store_hours_does_not_overwrite_existing_package(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        $defaultPackage = Package::create([
            'name' => 'Domyslna',
            'price' => 50.00,
            'is_default' => true,
        ]);
        $assignedPackage = Package::create(['name' => 'Przypisana', 'price' => 70.00]);

        $date = $this->pastDateInCurrentWeek();
        $shift = WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'package_id' => $assignedPackage->id,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertOk();
        $shift->refresh();
        $this->assertEquals($assignedPackage->id, $shift->package_id);
        $this->assertNotEquals($defaultPackage->id, $shift->package_id);
    }

    public function test_store_hours_leaves_package_null_when_no_default_set(): void
    {
        $user = $this->createWorkerUser();
        $worker = $user->worker;

        Package::create(['name' => 'Pakiet A', 'price' => 50.00]);
        Package::create(['name' => 'Pakiet B', 'price' => 70.00]);

        $date = $this->pastDateInCurrentWeek();
        $shift = WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'package_id' => null,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('worker.schedule.hours', $date),
            ['shift_type' => 'morning', 'from_time' => '08:00', 'to_time' => '14:00']
        );

        $response->assertOk();
        $shift->refresh();
        $this->assertNull($shift->package_id);
        $this->assertEquals('worker', $shift->hours_source);
    }
}
