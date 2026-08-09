<?php

namespace Tests\Fakes;

use App\Contracts\HtmlExportRenderer;

class FakeHtmlExportRenderer implements HtmlExportRenderer
{
    /** @var array<int, array{html: string, path: string, landscape: bool}> */
    public array $pdfCalls = [];

    /** @var array<int, array{pdfHtml: string, pngHtml: string, pdfPath: string, pngPath: string}> */
    public array $weeklyCalls = [];

    public function savePdf(
        string $html,
        string $path,
        bool $landscape = false,
    ): void {
        $this->pdfCalls[] = compact('html', 'path', 'landscape');
        file_put_contents($path, '%PDF-1.4 test');
    }

    public function saveWeeklyAssets(
        string $pdfHtml,
        string $pngHtml,
        string $pdfPath,
        string $pngPath,
    ): void {
        $this->weeklyCalls[] = compact('pdfHtml', 'pngHtml', 'pdfPath', 'pngPath');
        file_put_contents($pdfPath, '%PDF-1.4 test');
        file_put_contents($pngPath, "\x89PNG\r\n\x1a\n");
    }
}
