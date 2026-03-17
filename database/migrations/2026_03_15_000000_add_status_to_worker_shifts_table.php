<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('worker_shifts', 'status')) {
            return;
        }

        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->string('status')->default('worked')->after('minutes');
        });
    }

    public function down(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
