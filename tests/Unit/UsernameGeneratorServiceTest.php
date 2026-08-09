<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UsernameGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsernameGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsernameGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UsernameGeneratorService;
    }

    public function test_generates_basic_username(): void
    {
        $username = $this->service->generate('Jan', 'Kowalski');

        $this->assertEquals('j.kowalski', $username);
    }

    public function test_normalizes_polish_characters(): void
    {
        $username = $this->service->generate('Łukasz', 'Żółćkowski');

        $this->assertEquals('l.zolckowski', $username);
    }

    public function test_expands_first_name_on_collision(): void
    {
        User::create(['username' => 'j.kowalski', 'email' => 'j.kowalski@example.test', 'password' => 'pass']);

        $username = $this->service->generate('Jan', 'Kowalski');

        $this->assertEquals('ja.kowalski', $username);
    }

    public function test_expands_full_first_name_then_adds_number(): void
    {
        User::create(['username' => 'j.kowalski', 'email' => 'j.kowalski@example.test', 'password' => 'pass']);
        User::create(['username' => 'ja.kowalski', 'email' => 'ja.kowalski@example.test', 'password' => 'pass']);
        User::create(['username' => 'jan.kowalski', 'email' => 'jan.kowalski@example.test', 'password' => 'pass']);

        $username = $this->service->generate('Jan', 'Kowalski');

        $this->assertEquals('jan.kowalski1', $username);
    }

    public function test_increments_number_on_further_collisions(): void
    {
        User::create(['username' => 'j.kowalski', 'email' => 'j.kowalski@example.test', 'password' => 'pass']);
        User::create(['username' => 'ja.kowalski', 'email' => 'ja.kowalski@example.test', 'password' => 'pass']);
        User::create(['username' => 'jan.kowalski', 'email' => 'jan.kowalski@example.test', 'password' => 'pass']);
        User::create(['username' => 'jan.kowalski1', 'email' => 'jan.kowalski1@example.test', 'password' => 'pass']);

        $username = $this->service->generate('Jan', 'Kowalski');

        $this->assertEquals('jan.kowalski2', $username);
    }

    public function test_handles_lowercase(): void
    {
        $username = $this->service->generate('ANNA', 'NOWAK');

        $this->assertEquals('a.nowak', $username);
    }

    public function test_strips_special_characters(): void
    {
        $username = $this->service->generate('Jean-Pierre', "O'Brien");

        $this->assertEquals('j.obrien', $username);
    }
}
