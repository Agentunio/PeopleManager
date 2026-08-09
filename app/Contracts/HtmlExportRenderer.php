<?php

namespace App\Contracts;

interface HtmlExportRenderer
{
    public function savePdf(
        string $html,
        string $path,
        bool $landscape = false,
    ): void;

    public function saveWeeklyAssets(
        string $pdfHtml,
        string $pngHtml,
        string $pdfPath,
        string $pngPath,
    ): void;
}
