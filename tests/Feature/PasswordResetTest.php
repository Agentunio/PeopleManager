<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use App\Notifications\QueuedResetPassword as ResetPasswordNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'OldPassword1!',
            'role' => 'admin',
            'is_active' => true,
        ], $attributes));
    }

    public function test_login_page_links_to_forgot_password_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false)
            ->assertSee('Zapomniałeś hasła?');
    }

    public function test_forgot_password_form_loads(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Odzyskaj hasło')
            ->assertSee('Adres e-mail');
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $this->post(route('password.email'), ['email' => 'invalid-email'])
            ->assertSessionHasErrors('email');
    }

    /**
     * QueuedResetPassword implements ShouldQueue, so on the database driver the
     * request writes to the `jobs` table. Without that migration the whole flow
     * dies with a 500 ("Table 'jobs' doesn't exist") — this pins the migration
     * in place rather than trusting the sync driver used elsewhere in tests.
     */
    public function test_reset_link_is_queued_on_database_driver(): void
    {
        config(['queue.default' => 'database']);
        $user = $this->createUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_reset_link_is_sent_to_active_admin(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->post(route('password.email'), ['email' => ' ADMIN@example.com '])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_link_is_sent_to_active_worker(): void
    {
        Notification::fake();
        $user = $this->createUser([
            'username' => 'worker',
            'email' => 'worker@example.com',
            'role' => 'worker',
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_response_does_not_reveal_missing_or_inactive_account(): void
    {
        Notification::fake();
        $inactiveUser = $this->createUser([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $missingResponse = $this->post(route('password.email'), ['email' => 'missing@example.com']);
        $inactiveResponse = $this->post(route('password.email'), ['email' => $inactiveUser->email]);

        $missingResponse->assertSessionHas('status');
        $inactiveResponse->assertSessionHas('status');
        $this->assertSame(
            $missingResponse->getSession()->get('status'),
            $inactiveResponse->getSession()->get('status')
        );
        Notification::assertNothingSent();
    }

    public function test_reset_form_loads_with_email_and_token(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Ustaw nowe hasło')
            ->assertSee($user->email);
    }

    public function test_password_can_be_reset_once_with_valid_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->createUser(['remember_token' => 'old-remember-token']);
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword2@',
            'password_confirmation' => 'NewPassword2@',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword2@', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        Event::assertDispatched(PasswordReset::class);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPassword3#',
            'password_confirmation' => 'AnotherPassword3#',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('NewPassword2@', $user->fresh()->password));
    }

    public function test_reset_rejects_weak_or_unconfirmed_password(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('OldPassword1!', $user->fresh()->password));
    }

    public function test_reset_uses_polish_password_rule_messages(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'lowercase1!',
            'password_confirmation' => 'lowercase1!',
        ])->assertSessionHasErrors([
            'password' => 'Hasło musi zawierać małą i wielką literę.',
        ]);
    }

    public function test_inactive_account_cannot_use_existing_reset_token(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);
        $user->update(['is_active' => false]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword2@',
            'password_confirmation' => 'NewPassword2@',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPassword1!', $user->fresh()->password));
    }

    public function test_database_sessions_are_invalidated_after_reset(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        DB::table('sessions')->insert([
            'id' => 'existing-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword2@',
            'password_confirmation' => 'NewPassword2@',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => 'existing-session']);
    }

    public function test_reset_token_is_stored_as_hash(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);
        $storedToken = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->value('token');

        $this->assertNotSame($token, $storedToken);
        $this->assertTrue(Hash::check($token, $storedToken));
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);
        $this->travel(61)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword2@',
            'password_confirmation' => 'NewPassword2@',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPassword1!', $user->fresh()->password));
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        $this->createUser([
            'username' => 'limited',
            'email' => 'limited@example.com',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), [
                'email' => 'limited@example.com',
            ])->assertRedirect();
        }

        $this->post(route('password.email'), [
            'email' => 'limited@example.com',
        ])->assertStatus(429);
    }

    public function test_user_email_is_required_by_database(): void
    {
        $this->expectException(QueryException::class);

        User::create([
            'username' => 'without-email',
            'password' => 'Password1!',
            'role' => 'worker',
            'is_active' => true,
        ]);
    }
}
