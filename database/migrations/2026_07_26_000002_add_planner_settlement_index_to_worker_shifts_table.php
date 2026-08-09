<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            // (is_draft, day, minutes) serves both the settlement checks
            // (is_draft + day + minutes IS NULL, index-only) and the published
            // day-range aggregates like getCostByShift (is_draft + day), which
            // a minutes-second column order would force into a full scan.
            $table->index(
                ['is_draft', 'day', 'minutes'],
                'worker_shifts_planner_settlement_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->dropIndex('worker_shifts_planner_settlement_index');
        });
    }
};
