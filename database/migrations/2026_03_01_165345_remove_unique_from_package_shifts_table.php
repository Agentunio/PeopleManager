<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_shifts', function (Blueprint $table) {
            $table->dropUnique(['day', 'shift_type']);
            $table->index(['day', 'shift_type']);
        });
    }

    public function down(): void
    {
        Schema::table('package_shifts', function (Blueprint $table) {
            $table->dropIndex(['day', 'shift_type']);
            $table->unique(['day', 'shift_type']);
        });
    }
};
