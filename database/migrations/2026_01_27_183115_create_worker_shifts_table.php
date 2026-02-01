<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('worker_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->onDelete('cascade');
            $table->date('day');
            $table->enum('shift_type',['morning','afternoon']);
            $table->foreignId('package_id')->nullable()->constrained('packages')->onDelete('cascade');
            $table->integer('minutes')->nullable();
            $table->timestamps();
            $table->unique(['worker_id', 'day', 'shift_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_shifts');
    }
};
