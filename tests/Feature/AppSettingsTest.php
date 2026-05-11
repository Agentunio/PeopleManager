<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

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

    private function pastDateInCurrentWeek(): string
    {
        $yesterday = now()->subDay();
        if ($yesterday->lt(now()->startOfWeek())) {
            $yesterday = now()->startOfWeek();
        }
        return $yesterday->toDateString();
    }

    public function test_admin_can_view_settings_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('app-settings.index'));

        $response->assertStatus(200);
        $response->assertSee('Ustawienia');
        $response->assertSee('worker_self_hours_enabled', false);
        $response->assertSee('settings-toggle-input', false);
    }

    public function test_non_admin_cannot_view_settings_page(): void
    {
        $worker = $this->createWorkerUser();

        $response = $this->actingAs($worker)->get(route('app-settings.index'));

        $response->assertStatus(403);
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get(route('app-settings.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_enable_setting(): void
    {
        $admin = $this->createAdmin();
        AppSetting::set(AppSetting::KEY_WORKER_SELF_HOURS, false);

        $response = $this->actingAs($admin)->post(route('app-settings.update'), [
            'worker_self_hours_enabled' => '1',
        ]);

        $response->assertRedirect(route('app-settings.index'));
        $this->assertTrue(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }

    public function test_admin_can_disable_setting_when_no_pending(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('app-settings.update'), []);

        $response->assertRedirect(route('app-settings.index'));
        $this->assertFalse(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }

    public function test_disable_with_pending_returns_warning_view(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $date = $this->pastDateInCurrentWeek();

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'hours_source' => 'worker',
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
        ]);

        $response = $this->actingAs($admin)->post(route('app-settings.update'), []);

        $response->assertStatus(200);
        $response->assertSee('Jan Kowalski');
        $response->assertSee(Carbon::parse($date)->translatedFormat('d.m.Y'));
        $response->assertSee('Poranna');
        $response->assertSee('Wylacz mimo wszystko');
        $this->assertTrue(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }

    public function test_disable_with_pending_and_force_succeeds(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $this->pastDateInCurrentWeek(),
            'shift_type' => 'morning',
            'hours_source' => 'worker',
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
        ]);

        $response = $this->actingAs($admin)->post(route('app-settings.update'), [
            'force_disable_with_pending' => '1',
        ]);

        $response->assertRedirect(route('app-settings.index'));
        $this->assertFalse(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }

    public function test_default_value_is_true_after_migration(): void
    {
        $this->assertTrue(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }

    public function test_get_bool_returns_false_when_record_missing(): void
    {
        AppSetting::find(AppSetting::KEY_WORKER_SELF_HOURS)->delete();

        $this->assertFalse(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }

    public function test_pending_query_uses_published_scope(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Draftowy']);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $this->pastDateInCurrentWeek(),
            'shift_type' => 'morning',
            'hours_source' => 'worker',
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
            'is_draft' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('app-settings.update'), []);

        $response->assertRedirect(route('app-settings.index'));
        $response->assertDontSee('Jan Draftowy');
        $this->assertFalse(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }

    public function test_pending_query_excludes_substitutes(): void
    {
        $admin = $this->createAdmin();
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Zastepca']);
        $original = Worker::create(['first_name' => 'Pierwotny', 'last_name' => 'Pracownik']);

        $originalShift = WorkerShift::create([
            'worker_id' => $original->id,
            'day' => $this->pastDateInCurrentWeek(),
            'shift_type' => 'morning',
        ]);

        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => $this->pastDateInCurrentWeek(),
            'shift_type' => 'morning',
            'hours_source' => 'worker',
            'worker_from_time' => 8 * 60,
            'worker_to_time' => 14 * 60,
            'substituted_for_shift_id' => $originalShift->id,
        ]);

        $response = $this->actingAs($admin)->post(route('app-settings.update'), []);

        $response->assertRedirect(route('app-settings.index'));
        $this->assertFalse(AppSetting::getBool(AppSetting::KEY_WORKER_SELF_HOURS));
    }
}
