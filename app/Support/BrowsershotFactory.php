<?php

namespace App\Support;

use Spatie\Browsershot\Browsershot;

class BrowsershotFactory
{
    /**
     * Browsershot instance preconfigured for the containerised Chromium.
     *
     * Chromium resolves its Crashpad database below XDG_CONFIG_HOME. PHP-FPM runs
     * as www-data with an unwritable HOME, so Chromium cannot initialise it in
     * the container and aborts with "--database is required", and the default
     * 64MB /dev/shm is too small for the renderer. The writable XDG path and
     * launch flags remain centralised here so every export uses the same tested
     * profile.
     */
    public static function make(string $html): Browsershot
    {
        return Browsershot::html($html)
            ->setChromePath(config('services.chrome.path'))
            ->setEnvironmentOptions([
                'XDG_CONFIG_HOME' => storage_path('app/temp/chromium'),
            ])
            ->noSandbox()
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-crashpad',
                'disable-gpu',
            ]);
    }
}
