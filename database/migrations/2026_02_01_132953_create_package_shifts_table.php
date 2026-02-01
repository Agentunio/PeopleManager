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
        Schema::create('package_shifts', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->enum('shift_type', ['morning', 'afternoon']);
            $table->integer('packages_count')->default(0);
            $table->foreignId('package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->timestamps();

            $table->unique(['day', 'shift_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_shifts');
    }
};
