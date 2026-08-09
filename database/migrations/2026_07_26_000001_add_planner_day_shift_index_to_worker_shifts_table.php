<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->index(
                ['day', 'shift_type'],
                'worker_shifts_planner_day_shift_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->dropIndex('worker_shifts_planner_day_shift_index');
        });
    }
};
