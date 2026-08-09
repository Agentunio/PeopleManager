<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('type', ['disabled', 'signup', 'always', 'admin'])
                ->default('disabled')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('schedules')->where('type', 'admin')->update([
            'type' => 'disabled',
            'start_date' => null,
            'end_date' => null,
            'signup_deadline' => null,
        ]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('type', ['disabled', 'signup', 'always'])
                ->default('disabled')
                ->change();
        });
    }
};
