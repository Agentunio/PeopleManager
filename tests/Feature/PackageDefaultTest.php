<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageDefaultTest extends TestCase
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

    public function test_set_default_marks_only_selected_package(): void
    {
        $admin = $this->createAdmin();
        $a = Package::create(['name' => 'A', 'price' => 50, 'is_default' => true]);
        $b = Package::create(['name' => 'B', 'price' => 70]);

        $response = $this->actingAs($admin)->post(route('settings.packages.default'), [
            'package_id' => $b->id,
        ]);

        $response->assertRedirect();
        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertEquals(1, Package::where('is_default', true)->count());
    }

    public function test_set_default_clears_when_null_passed(): void
    {
        $admin = $this->createAdmin();
        $a = Package::create(['name' => 'A', 'price' => 50, 'is_default' => true]);

        $response = $this->actingAs($admin)->post(route('settings.packages.default'), [
            'package_id' => null,
        ]);

        $response->assertRedirect();
        $this->assertFalse($a->fresh()->is_default);
        $this->assertEquals(0, Package::where('is_default', true)->count());
    }

    public function test_set_default_rejects_invalid_package_id(): void
    {
        $admin = $this->createAdmin();
        Package::create(['name' => 'A', 'price' => 50, 'is_default' => true]);

        $response = $this->actingAs($admin)->post(route('settings.packages.default'), [
            'package_id' => 9999,
        ]);

        $response->assertSessionHasErrors('package_id');
        $this->assertEquals(1, Package::where('is_default', true)->count());
    }

    public function test_settings_page_uses_new_url(): void
    {
        $admin = $this->createAdmin();
        Package::create(['name' => 'A', 'price' => 50]);

        $response = $this->actingAs($admin)->get('/stawki');

        $response->assertOk();
        $response->assertSee('Domyślna stawka dla godzin pracownika', false);
    }

    public function test_app_settings_url_is_not_package_settings_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/ustawienia');

        $response->assertOk();
        $response->assertSee('Samodzielne wpisywanie czasu pracy');
        $response->assertDontSee('stawka dla godzin pracownika', false);
    }
}
