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
            'email' => 'admin@example.test',
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
        $response->assertSee('Stawki za paczkę używane przy rozliczeniach', false);
    }

    public function test_app_settings_url_is_not_package_settings_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/ustawienia');

        $response->assertOk();
        $response->assertSee('Samodzielne wpisywanie godzin pracy');
        $response->assertDontSee('stawka dla godzin pracownika', false);
    }

    public function test_invalid_package_update_reopens_edited_row(): void
    {
        $admin = $this->createAdmin();
        $package = Package::create(['name' => 'Standardowa', 'price' => 50]);

        $response = $this->actingAs($admin)
            ->from(route('settings.index'))
            ->put(route('settings.packages.update', $package), [
                'editing_package_id' => (string) $package->id,
                'name' => 'Zmieniona',
                'price' => -1,
            ]);

        $response
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('price')
            ->assertSessionHasInput('editing_package_id', (string) $package->id);

        $page = $this->get(route('settings.index'));
        $page->assertOk()->assertSee('Zmieniona');
        $content = $page->getContent();

        $this->assertMatchesRegularExpression(
            '/id="toggle-form-'.$package->id.'"[^>]*checked/s',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="toggle-package-form"[^>]*checked/s',
            $content
        );
    }
}
