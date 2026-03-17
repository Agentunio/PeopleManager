<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('substituted_for_shift_id')->nullable()->after('status');
            $table->foreign('substituted_for_shift_id')
                ->references('id')
                ->on('worker_shifts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->dropForeign(['substituted_for_shift_id']);
            $table->dropColumn('substituted_for_shift_id');
        });
    }
};
