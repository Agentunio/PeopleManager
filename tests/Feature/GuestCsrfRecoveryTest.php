<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
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

    private function renderTokenMismatchResponse(Request $request): TestResponse
    {
        $request->setLaravelSession($this->app['session.store']);

        $response = $this->app
            ->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        return TestResponse::fromBaseResponse($response);
    }
}
