<?php

namespace Tests\Feature;

use App\Contracts\HtmlExportRenderer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeHtmlExportRenderer;
use Tests\TestCase;
use ZipArchive;

class WeeklyExportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'weekly_admin',
            'email' => 'weekly-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    public function test_export_validates_week_start_as_a_strict_iso_date(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('planner.export.week'), ['week_start' => '06.04.2026'])
            ->assertSessionHasErrors('week_start');
    }

    public function test_export_is_an_immediate_zip_with_pdf_and_png(): void
    {
        $fakeRenderer = new FakeHtmlExportRenderer;
        $this->app->instance(HtmlExportRenderer::class, $fakeRenderer);

        $response = $this->actingAs($this->createAdmin())
            ->post(route('planner.export.week'), ['week_start' => '2026-04-08']);

        $response->assertDownload('grafik_2026-04-06.zip');
        $this->assertCount(1, $fakeRenderer->weeklyCalls);

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertNotFalse($zip->locateName('grafik_2026-04-06.pdf'));
        $this->assertNotFalse($zip->locateName('grafik_2026-04-06.png'));
        $zip->close();

        @unlink($zipPath);
    }
}
