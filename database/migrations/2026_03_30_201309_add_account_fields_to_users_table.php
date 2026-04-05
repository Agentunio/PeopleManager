<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('username');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('activation_token', 64)->nullable()->unique()->after('is_active');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'is_active', 'activation_token', 'activation_expires_at']);
        });
    }
};
