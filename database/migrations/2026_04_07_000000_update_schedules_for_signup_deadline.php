<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite cannot ALTER column check constraints in place — recreate the table.
            Schema::dropIfExists('schedules');
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['disabled', 'signup', 'always'])->default('disabled');
                $table->datetime('start_date')->nullable();
                $table->datetime('end_date')->nullable();
                $table->datetime('signup_deadline')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('schedules', function (Blueprint $table) {
            $table->datetime('signup_deadline')->nullable()->after('end_date');
        });

        DB::table('schedules')->update([
            'type' => 'disabled',
            'start_date' => null,
            'end_date' => null,
            'signup_deadline' => null,
        ]);

        DB::statement("ALTER TABLE schedules MODIFY type ENUM('disabled','signup','always') NOT NULL DEFAULT 'disabled'");
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::dropIfExists('schedules');
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['disabled', 'range', 'week', 'always'])->default('disabled');
                $table->datetime('start_date')->nullable();
                $table->datetime('end_date')->nullable();
                $table->timestamps();
            });
            return;
        }

        DB::statement("ALTER TABLE schedules MODIFY type ENUM('disabled','range','week','always') NOT NULL DEFAULT 'disabled'");

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('signup_deadline');
        });
    }
};
