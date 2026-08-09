<?php

namespace Tests\Feature;

use App\Mail\WorkerAccountActivated;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    private const RAW_TOKEN = 'valid_test_token_1234567890abcdef';

    private function createPendingAccount(): User
    {
        $worker = Worker::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'date_of_birth' => '1990-05-15',
        ]);

        return User::create([
            'username' => 'j.kowalski',
            'password' => 'temporary',
            'email' => 'jan@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
            'activation_token' => hash('sha256', self::RAW_TOKEN),
            'activation_expires_at' => now()->addDay(),
        ]);
    }

    private function verifyDob(string $token = self::RAW_TOKEN, string $dob = '1990-05-15'): TestResponse
    {
        return $this->post(route('account.verify', $token), ['date_of_birth' => $dob]);
    }

    private function activateAccount(string $token = self::RAW_TOKEN, string $password = 'SecurePass1!'): TestResponse
    {
        return $this->post(route('account.activate.store', $token), [
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    // --- Strona aktywacji ---

    public function test_activation_page_loads(): void
    {
        $this->createPendingAccount();

        $response = $this->get(route('account.activate', self::RAW_TOKEN));

        $response->assertStatus(200);
        $response->assertSee('Aktywacja konta');
        $response->assertSee('Data urodzenia');
        $response->assertSee('Wybierz rok');
        $response->assertSee('Wybierz miesiąc');
        $response->assertSee('Wybierz dzień');
        $response->assertSee('date-picker-dialog');
        $response->assertDontSee('date_of_birth_display');
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get(route('account.activate', 'nonexistent_token'));

        $response->assertSee('Link aktywacyjny jest nieprawidłowy');
        $response->assertSee('Wróć do logowania');
        $response->assertStatus(404);
    }

    public function test_expired_token_returns_410(): void
    {
        $user = $this->createPendingAccount();
        $user->update(['activation_expires_at' => now()->subHour()]);

        $response = $this->get(route('account.activate', self::RAW_TOKEN));

        $response->assertSee('Link aktywacyjny wygasł');
        $response->assertSee('Wróć do logowania');
        $response->assertStatus(410);
    }

    // --- Weryfikacja DOB ---

    public function test_correct_dob_shows_password_form(): void
    {
        $this->createPendingAccount();

        $response = $this->verifyDob();

        $response->assertStatus(200);
        $response->assertSee('Nowe hasło');
    }

    public function test_incorrect_dob_shows_error(): void
    {
        $this->createPendingAccount();

        $response = $this->verifyDob(dob: '1995-01-01');

        $response->assertRedirect();
        $response->assertSessionHasErrors('date_of_birth');
    }

    public function test_verify_with_empty_dob_rejected(): void
    {
        $this->createPendingAccount();

        $response = $this->post(route('account.verify', self::RAW_TOKEN), ['date_of_birth' => '']);
        $response->assertSessionHasErrors('date_of_birth');
    }

    public function test_verify_with_invalid_date_format_rejected(): void
    {
        $this->createPendingAccount();

        $response = $this->verifyDob(dob: 'not-a-date');
        $response->assertSessionHasErrors('date_of_birth');
    }

    public function test_verify_without_dob_field_rejected(): void
    {
        $this->createPendingAccount();

        $response = $this->post(route('account.verify', self::RAW_TOKEN), []);
        $response->assertSessionHasErrors('date_of_birth');
    }

    // --- Aktywacja z sesją ---

    public function test_successful_activation(): void
    {
        $user = $this->createPendingAccount();

        $this->verifyDob();
        $response = $this->activateAccount();

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->activation_token);
        $this->assertNull($user->activation_expires_at);
    }

    public function test_successful_activation_sends_confirmation_email(): void
    {
        Mail::fake();

        $user = $this->createPendingAccount();

        $this->verifyDob();
        $this->activateAccount();

        Mail::assertQueued(WorkerAccountActivated::class, function (WorkerAccountActivated $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->user->id === $user->id;
        });
    }

    public function test_failed_activation_does_not_send_email(): void
    {
        Mail::fake();

        $this->createPendingAccount();

        $this->verifyDob();
        $this->activateAccount(password: 'weak');

        Mail::assertNotSent(WorkerAccountActivated::class);
    }

    public function test_activation_without_verification_rejected(): void
    {
        $this->createPendingAccount();

        $response = $this->activateAccount();
        $response->assertStatus(403);
    }

    public function test_activation_without_session_verification_rejected(): void
    {
        $user = $this->createPendingAccount();

        $response = $this->withSession([])->post(
            route('account.activate.store', self::RAW_TOKEN),
            [
                'password' => 'SecurePass1!',
                'password_confirmation' => 'SecurePass1!',
            ]
        );

        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_session_invalidated_after_activation(): void
    {
        $this->createPendingAccount();

        $this->verifyDob();
        $this->activateAccount();

        $this->assertNull(session('activation_verified_'.self::RAW_TOKEN));
    }

    // --- Hasła ---

    public function test_weak_password_rejected(): void
    {
        $user = $this->createPendingAccount();

        $this->verifyDob();
        $response = $this->activateAccount(password: 'weak');

        $response->assertSessionHasErrors('password');
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_activation_uses_polish_password_rule_messages(): void
    {
        $this->createPendingAccount();
        $this->verifyDob();

        $this->activateAccount(password: 'lowercase1!')->assertSessionHasErrors([
            'password' => 'Hasło musi zawierać małą i wielką literę.',
        ]);
    }

    #[DataProvider('weakPasswordsProvider')]
    public function test_specific_password_requirement_enforced(string $password, string $missingRule): void
    {
        $user = $this->createPendingAccount();

        $this->verifyDob();
        $response = $this->activateAccount(password: $password);

        $response->assertSessionHasErrors('password');
        $this->assertFalse($user->fresh()->is_active, "Password '{$password}' should be rejected (missing: {$missingRule})");
    }

    public static function weakPasswordsProvider(): array
    {
        return [
            'too short' => ['Ab1!', 'min length'],
            'no uppercase' => ['abcdefg1!', 'uppercase'],
            'no lowercase' => ['ABCDEFG1!', 'lowercase'],
            'no number' => ['Abcdefgh!', 'number'],
            'no special char' => ['Abcdefg1', 'special character'],
            'only numbers' => ['12345678', 'mixed case + special'],
            'only letters' => ['Abcdefgh', 'number + special'],
            'spaces only padding' => ['       !', 'mixed case + number'],
        ];
    }

    public function test_password_mismatch_rejected(): void
    {
        $user = $this->createPendingAccount();

        $this->verifyDob();

        $response = $this->post(route('account.activate.store', self::RAW_TOKEN), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'DifferentPass1!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_empty_password_rejected(): void
    {
        $user = $this->createPendingAccount();

        $this->verifyDob();
        $response = $this->activateAccount(password: '');

        $response->assertSessionHasErrors('password');
        $this->assertFalse($user->fresh()->is_active);
    }

    // --- Token expiry ---

    public function test_token_expires_after_exact_time(): void
    {
        $this->createPendingAccount();

        Carbon::setTestNow(now()->addHours(23)->addMinutes(59));
        $response = $this->get(route('account.activate', self::RAW_TOKEN));
        $response->assertStatus(200);

        Carbon::setTestNow(now()->addDay()->addMinute());
        $response = $this->get(route('account.activate', self::RAW_TOKEN));
        $response->assertStatus(410);

        Carbon::setTestNow();
    }

    public function test_token_with_1_minute_expiry(): void
    {
        $worker = Worker::create([
            'first_name' => 'Test',
            'last_name' => 'Minutowy',
            'date_of_birth' => '1995-03-20',
        ]);

        User::create([
            'username' => 't.minutowy',
            'password' => 'temporary',
            'email' => 'test.min@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
            'activation_token' => hash('sha256', 'one_minute_token_abcdef1234567890'),
            'activation_expires_at' => now()->addMinute(),
        ]);

        $response = $this->get(route('account.activate', 'one_minute_token_abcdef1234567890'));
        $response->assertStatus(200);

        Carbon::setTestNow(now()->addSeconds(30));
        $response = $this->get(route('account.activate', 'one_minute_token_abcdef1234567890'));
        $response->assertStatus(200);

        Carbon::setTestNow(now()->addMinutes(2));
        $response = $this->get(route('account.activate', 'one_minute_token_abcdef1234567890'));
        $response->assertStatus(410);

        Carbon::setTestNow();
    }

    public function test_full_activation_flow_before_expiry(): void
    {
        $baseTime = Carbon::now();
        $token = 'short_lived_token_xyz1234567890ab';

        $worker = Worker::create([
            'first_name' => 'Ewa',
            'last_name' => 'Szybka',
            'date_of_birth' => '1988-07-10',
        ]);

        $user = User::create([
            'username' => 'e.szybka',
            'password' => 'temporary',
            'email' => 'ewa@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
            'activation_token' => hash('sha256', $token),
            'activation_expires_at' => $baseTime->copy()->addMinutes(5),
        ]);

        Carbon::setTestNow($baseTime->copy()->addMinutes(3));
        $this->verifyDob($token, '1988-07-10');

        Carbon::setTestNow($baseTime->copy()->addMinutes(4));
        $response = $this->activateAccount($token);

        $response->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->is_active);

        Carbon::setTestNow();
    }

    public function test_full_activation_flow_after_expiry_rejected(): void
    {
        $baseTime = Carbon::now();
        $token = 'expiring_soon_token_abc123456789a';

        $worker = Worker::create([
            'first_name' => 'Marek',
            'last_name' => 'Spozniony',
            'date_of_birth' => '1992-11-25',
        ]);

        $user = User::create([
            'username' => 'm.spozniony',
            'password' => 'temporary',
            'email' => 'marek@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => false,
            'activation_token' => hash('sha256', $token),
            'activation_expires_at' => $baseTime->copy()->addMinutes(5),
        ]);

        Carbon::setTestNow($baseTime->copy()->addMinutes(2));
        $this->verifyDob($token, '1992-11-25');

        Carbon::setTestNow($baseTime->copy()->addMinutes(10));
        $response = $this->activateAccount($token);

        $response->assertStatus(410);
        $this->assertFalse($user->fresh()->is_active);

        Carbon::setTestNow();
    }
}
