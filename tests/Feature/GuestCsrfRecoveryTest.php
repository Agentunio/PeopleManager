<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GuestCsrfRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_login_token_mismatch_redirects_to_login_with_error(): void
    {
        $response = $this->renderTokenMismatchResponse(Request::create('/', 'POST'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_authenticated_worker_duplicate_login_post_redirects_to_dashboard(): void
    {
        $worker = Worker::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $user = User::create([
            'username' => 'j.kowalski',
            'password' => 'secret',
            'email' => 'jan@example.com',
            'worker_id' => $worker->id,
            'role' => 'worker',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->renderTokenMismatchResponse(Request::create('/', 'POST'));

        $response->assertRedirect(route('worker.dashboard'));
    }

    public function test_activation_token_mismatch_redirects_to_login_with_error(): void
    {
        $request = Request::create('/aktywacja/test-token/activate', 'POST');
        $request->setRouteResolver(fn () => (new Route('POST', '/aktywacja/{token}/activate', []))
            ->name('account.activate.store'));

        $response = $this->renderTokenMismatchResponse($request);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_forgot_password_token_mismatch_redirects_to_form_with_error(): void
    {
        $request = Request::create('/zapomniane-haslo', 'POST');
        $request->setRouteResolver(fn () => (new Route('POST', '/zapomniane-haslo', []))
            ->name('password.email'));

        $response = $this->renderTokenMismatchResponse($request);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors('email');
    }

    public function test_reset_password_token_mismatch_redirects_back_with_error(): void
    {
        $request = Request::create('/reset-hasla', 'POST', [
            'email' => 'admin@example.com',
            'token' => str_repeat('a', 64),
        ]);
        $request->headers->set('referer', route('password.reset', [
            'token' => str_repeat('a', 64),
            'email' => 'admin@example.com',
        ]));
        $request->setRouteResolver(fn () => (new Route('POST', '/reset-hasla', []))
            ->name('password.update'));

        $response = $this->renderTokenMismatchResponse($request);

        $response->assertRedirect(route('password.reset', [
            'token' => str_repeat('a', 64),
            'email' => 'admin@example.com',
        ]));
        $response->assertSessionHasErrors('password');
    }

    private function renderTokenMismatchResponse(Request $request): TestResponse
    {
        $request->setLaravelSession($this->app['session.store']);

        $response = $this->app
            ->make(ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        return TestResponse::fromBaseResponse($response);
    }
}
