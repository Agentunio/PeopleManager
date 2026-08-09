<?php

namespace Tests\Unit;

use App\Support\BrowsershotFactory;
use ReflectionClass;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

class BrowsershotFactoryTest extends TestCase
{
    public function test_it_configures_chromium_with_a_writable_xdg_directory(): void
    {
        config()->set('services.chrome.path', '/usr/bin/test-chromium');

        $browsershot = BrowsershotFactory::make('<h1>Test</h1>');
        $reflection = new ReflectionClass(Browsershot::class);

        $options = $reflection->getProperty('additionalOptions')->getValue($browsershot);
        $noSandbox = $reflection->getProperty('noSandbox')->getValue($browsershot);
        $chromiumArguments = $reflection->getProperty('chromiumArguments')->getValue($browsershot);

        $this->assertSame('/usr/bin/test-chromium', $options['executablePath']);
        $this->assertSame(storage_path('app/temp/chromium'), $options['env']['XDG_CONFIG_HOME']);
        $this->assertTrue($noSandbox);
        $this->assertContains('--disable-dev-shm-usage', $chromiumArguments);
        $this->assertContains('--disable-crashpad', $chromiumArguments);
        $this->assertContains('--disable-gpu', $chromiumArguments);
    }
}
