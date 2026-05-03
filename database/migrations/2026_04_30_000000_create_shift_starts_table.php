<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_starts', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->enum('shift_type', ['morning', 'afternoon']);
            $table->unsignedSmallInteger('start_time')->nullable();
            $table->timestamps();

            $table->unique(['day', 'shift_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_starts');
    }
};
