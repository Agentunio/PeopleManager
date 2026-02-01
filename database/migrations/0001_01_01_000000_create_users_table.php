<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'worker'])->default('worker');
            $table->timestamps();
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_user', 45);
            $table->boolean('success')->default(false);
            $table->timestamp('data')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('users');
    }
};
