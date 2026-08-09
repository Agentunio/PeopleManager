<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAssetLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('lightweightAdminRoutes')]
    public function test_new_admin_pages_do_not_load_unused_legacy_assets(string $routeName): void
    {
        $response = $this->actingAs($this->createAdmin())->get(route($routeName));

        $response
            ->assertOk()
            ->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/jquery', false)
            ->assertDontSee('fontawesome', false);
    }

    public function test_planner_day_uses_bundled_dependencies_instead_of_external_assets(): void
    {
        $response = $this->actingAs($this->createAdmin())->get(route('planner.day.index', now()->toDateString()));

        $response
            ->assertOk()
            ->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/jquery', false)
            ->assertDontSee('fontawesome', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lightweightAdminRoutes(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'workers' => ['workers.index'],
            'planner index' => ['planner.index'],
            'rates' => ['settings.index'],
            'app settings' => ['app-settings.index'],
        ];
    }

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'asset_admin',
            'email' => 'asset-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }
}
