<?php

namespace Tests\Feature;

use App\Mail\PasswordResetRequested;
use App\Mail\WorkerAccountActivated;
use App\Mail\WorkerAccountCreated;
use App\Models\User;
use App\Models\Worker;
use Tests\TestCase;

class MailTemplateTest extends TestCase
{
    public function test_account_created_email_uses_compatible_html_layout(): void
    {
        $html = (new WorkerAccountCreated($this->createUser(), 'raw-activation-token'))->render();

        $this->assertCompatibleLayout($html);
        $this->assertStringContainsString('Aktywuj konto', $html);
        $this->assertStringContainsString(route('account.activate', 'raw-activation-token'), $html);
        $this->assertStringContainsString('Link jest ważny przez 24 godziny', $html);
    }

    public function test_account_activated_email_uses_compatible_html_layout(): void
    {
        $html = (new WorkerAccountActivated($this->createUser()))->render();

        $this->assertCompatibleLayout($html);
        $this->assertStringContainsString('Konto aktywowane', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString('Jeśli nie aktywowałeś tego konta', $html);
    }

    public function test_password_reset_email_uses_compatible_html_layout(): void
    {
        $user = $this->createUser();
        $html = (new PasswordResetRequested($user, 'raw-reset-token'))->render();

        $this->assertCompatibleLayout($html);
        $this->assertStringContainsString('Ustaw nowe hasło', $html);
        $this->assertStringContainsString(route('password.reset', [
            'token' => 'raw-reset-token',
            'email' => $user->email,
        ]), $html);
        $this->assertStringContainsString('60 minut', $html);
        $this->assertStringContainsString('Jeśli nie prosiłeś', $html);
    }

    public function test_email_escapes_user_controlled_values(): void
    {
        $user = $this->createUser();
        $user->username = '<script>alert("username")</script>';
        $user->worker->first_name = '<img src=x onerror=alert("name")>';

        $html = (new WorkerAccountCreated($user, 'raw-activation-token'))->render();

        $this->assertStringNotContainsString('<script>alert("username")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert("name")>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;username&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(&quot;name&quot;)&gt;', $html);
    }

    private function assertCompatibleLayout(string $html): void
    {
        $this->assertStringContainsString('role="presentation"', $html);
        $this->assertStringContainsString('width="600"', $html);
        $this->assertStringContainsString('Sortownia Orlen Paczka', $html);
        $this->assertStringContainsString('j.kowalski', $html);
        $this->assertStringContainsString('bgcolor="#dc2626"', $html);
        $this->assertStringContainsString('Jeśli przycisk nie działa', $html);
        $this->assertStringContainsString('style="', $html);
        $this->assertStringNotContainsString('display: flex', $html);
        $this->assertStringNotContainsString('display: grid', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    private function createUser(): User
    {
        $worker = new Worker([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $user = new User([
            'username' => 'j.kowalski',
            'email' => 'jan@example.com',
        ]);
        $user->setRelation('worker', $worker);

        return $user;
    }
}
