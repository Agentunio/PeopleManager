<?php

namespace App\Services\Export;

use App\Contracts\HtmlExportRenderer;
use App\Support\BrowsershotFactory;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use JsonException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Process\Process;

class BrowsershotHtmlExportRenderer implements HtmlExportRenderer
{
    public function savePdf(
        string $html,
        string $path,
        bool $landscape = false,
    ): void {
        $this->withRenderLock(function () use ($html, $path, $landscape): void {
            $browsershot = BrowsershotFactory::make($html)
                ->format('A4')
                ->margins(10, 10, 10, 10);

            if ($landscape) {
                $browsershot->setOption('landscape', true);
            }

            $browsershot->save($path);
        });
    }

    /**
     * Render both weekly assets in one Chromium process. Starting Chromium is
     * the dominant export cost, so reusing the page avoids a second cold start.
     *
     * @throws JsonException
     */
    public function saveWeeklyAssets(
        string $pdfHtml,
        string $pngHtml,
        string $pdfPath,
        string $pngPath,
    ): void {
        $this->withRenderLock(function () use (
            $pdfHtml,
            $pngHtml,
            $pdfPath,
            $pngPath,
        ): void {
            $chromiumConfigPath = storage_path('app/temp/chromium');
            File::ensureDirectoryExists($chromiumConfigPath);

            $payload = json_encode([
                'chromePath' => config('services.chrome.path'),
                'pdfHtml' => $pdfHtml,
                'pngHtml' => $pngHtml,
                'pdfPath' => $pdfPath,
                'pngPath' => $pngPath,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $process = new Process(
                [
                    (string) config('services.node.binary', 'node'),
                    resource_path('js/render-weekly-export.mjs'),
                ],
                base_path(),
                ['XDG_CONFIG_HOME' => $chromiumConfigPath],
            );
            $process->setInput($payload);
            $process->setTimeout(120);
            $process->mustRun();
        });
    }

    private function withRenderLock(Closure $render): void
    {
        $lock = Cache::lock('html-export-renderer', 150);

        if (! $lock->get()) {
            throw new TooManyRequestsHttpException(
                5,
                'Trwa już generowanie innego eksportu. Spróbuj ponownie za chwilę.'
            );
        }

        try {
            $render();
        } finally {
            $this->releaseLock($lock);
        }
    }

    private function releaseLock(Lock $lock): void
    {
        $lock->release();
    }
}
