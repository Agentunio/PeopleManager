<?php

namespace App\Providers;

use App\Contracts\HtmlExportRenderer;
use App\Mail\PasswordResetRequested;
use App\Models\User;
use App\Models\Worker;
use App\Services\Export\BrowsershotHtmlExportRenderer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HtmlExportRenderer::class, BrowsershotHtmlExportRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        ResetPassword::toMailUsing(
            fn (User $user, string $token) => (new PasswordResetRequested($user, $token))->to($user->email)
        );

        RateLimiter::for('password-reset-email', function (Request $request): array {
            $emailHash = hash(
                'sha256',
                mb_strtolower(trim((string) $request->input('email')))
            );

            return [
                Limit::perMinutes(15, 5)
                    ->by('password-reset-email:'.$request->ip().':'.$emailHash),
                Limit::perMinutes(15, 20)
                    ->by('password-reset-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for(
            'password-reset-update',
            fn (Request $request) => Limit::perMinutes(15, 10)
                ->by('password-reset-update:'.$request->ip())
        );

        RateLimiter::for('exports', function (Request $request): array {
            $adminId = $this->rateLimitIdentity($request);

            return [
                Limit::perMinute(12)->by('exports-admin:'.$adminId),
                Limit::perMinute(40)->by('exports-ip:'.$request->ip()),
            ];
        });

        // Admin-triggered resets are keyed per admin and target worker, so resetting
        // many different workers in a row never exhausts a shared guest bucket.
        RateLimiter::for('admin-password-reset', function (Request $request): array {
            $adminId = $this->rateLimitIdentity($request);
            $worker = $request->route('worker');
            $workerId = (string) ($worker instanceof Worker ? $worker->id : $worker);

            return [
                Limit::perMinutes(15, 5)->by('admin-password-reset:'.$adminId.':'.$workerId),
                Limit::perMinutes(15, 30)->by('admin-password-reset-total:'.$adminId),
            ];
        });
    }

    private function rateLimitIdentity(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : $request->ip();
    }
}
