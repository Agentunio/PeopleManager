<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('users')
            ->where(function ($query): void {
                $query->whereNull('email')
                    ->orWhere('email', '');
            })
            ->get(['id', 'username']);

        // Backfill unique placeholders instead of aborting the whole migration
        // batch on production; flagged accounts are logged for manual follow-up.
        foreach ($missing as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['email' => "user-{$user->id}@brak-email.invalid"]);
        }

        if ($missing->isNotEmpty()) {
            Log::warning('require_email migration: backfilled placeholder e-mails, update them manually.', [
                'users' => $missing->map(fn ($user) => $user->id.':'.$user->username)->all(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }
};
