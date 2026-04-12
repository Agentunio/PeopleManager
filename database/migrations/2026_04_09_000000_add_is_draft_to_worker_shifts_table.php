<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('hours_source');
        });
    }

    public function down(): void
    {
        Schema::table('worker_shifts', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
