<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('approved_from_time')->nullable()->after('worker_to_time');
            $table->unsignedSmallInteger('approved_to_time')->nullable()->after('approved_from_time');
        });
    }

    public function down(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->dropColumn(['approved_from_time', 'approved_to_time']);
        });
    }
};
