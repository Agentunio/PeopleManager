<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequireEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigrationOnNullableColumn(): object
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        return require database_path('migrations/2026_07_12_000001_require_email_for_users_table.php');
    }

    public function test_backfills_missing_emails_with_unique_placeholders(): void
    {
        $migration = $this->runMigrationOnNullableColumn();

        $first = User::create(['username' => 'first', 'password' => 'password']);
        $second = User::create(['username' => 'second', 'password' => 'password']);
        DB::table('users')->where('id', $second->id)->update(['email' => '']);

        $migration->up();

        $this->assertSame("user-{$first->id}@brak-email.invalid", $first->fresh()->email);
        $this->assertSame("user-{$second->id}@brak-email.invalid", $second->fresh()->email);
    }

    public function test_existing_emails_are_left_untouched(): void
    {
        $migration = $this->runMigrationOnNullableColumn();

        $user = User::create([
            'username' => 'real',
            'password' => 'password',
            'email' => 'real@example.test',
        ]);

        $migration->up();

        $this->assertSame('real@example.test', $user->fresh()->email);
    }
}
