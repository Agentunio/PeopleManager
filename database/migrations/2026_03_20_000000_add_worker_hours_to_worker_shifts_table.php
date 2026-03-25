<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('worker_from_time')->nullable()->after('minutes');
            $table->unsignedSmallInteger('worker_to_time')->nullable()->after('worker_from_time');
            $table->enum('hours_source', ['worker', 'admin'])->nullable()->after('worker_to_time');
        });
    }

    public function down(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->dropColumn(['worker_from_time', 'worker_to_time', 'hours_source']);
        });
    }
};
