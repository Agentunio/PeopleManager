<?php

namespace Tests\Unit;

use App\Contracts\HtmlExportRenderer;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class HtmlExportRendererConcurrencyTest extends TestCase
{
    public function test_renderer_rejects_a_second_concurrent_chromium_process(): void
    {
        config(['cache.default' => 'array']);
        $lock = Cache::lock('html-export-renderer', 150);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(TooManyRequestsHttpException::class);

            app(HtmlExportRenderer::class)->saveWeeklyAssets(
                '<html></html>',
                '<html></html>',
                storage_path('app/temp/blocked-export.pdf'),
                storage_path('app/temp/blocked-export.png'),
            );
        } finally {
            $lock->release();
        }
    }
}
